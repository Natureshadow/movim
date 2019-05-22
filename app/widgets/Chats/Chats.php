<?php

use Movim\Widget\Base;

use Moxl\Xec\Action\Presence\Muc;
use Moxl\Xec\Action\Bookmark\Get;
use Moxl\Xec\Action\Bookmark\Set;

class Chats extends Base
{
    public function load()
    {
        $this->addcss('chats.css');
        $this->addjs('chats.js');
        $this->registerEvent('invitation', 'onMessage');
        $this->registerEvent('carbons', 'onMessage');
        $this->registerEvent('message', 'onMessage');
        $this->registerEvent('presence', 'onPresence', 'chat');
        $this->registerEvent('composing', 'onComposing', 'chat');
        $this->registerEvent('paused', 'onPaused', 'chat');
        $this->registerEvent('chat_open', 'onChatOpen', 'chat');
    }

    public function onMessage($packet)
    {
        $message = $packet->content;

        if ($message->type != 'groupchat'
         && $message->type != 'subject') {
            // If the message is from me
            if ($message->user_id == $message->jidto) {
                $from = $message->jidfrom;
            } else {
                $from = $message->jidto;
            }

            $this->ajaxOpen($from, false);
        }
    }

    public function onChatOpen($jid)
    {
        if (!$this->validateJid($jid)) {
            return;
        }

        $this->rpc(
            'MovimTpl.replace',
            '#' . cleanupId($jid . '_chat_item'),
            $this->prepareChat(
                $jid,
                $this->resolveContactFromJid($jid),
                $this->resolveRosterFromJid($jid),
                null,
                true
            )
        );
        $this->rpc('Chats.refresh');
    }

    public function onPresence($packet)
    {
        if ($packet->content != null) {
            $chats = \App\Cache::c('chats');
            if (is_array($chats) &&  array_key_exists($packet->content->jid, $chats)) {
                $this->rpc(
                    'MovimTpl.replace',
                    '#' . cleanupId($packet->content->jid.'_chat_item'),
                    $this->prepareChat(
                        $packet->content->jid,
                        $this->resolveContactFromJid($packet->content->jid),
                        $this->resolveRosterFromJid($packet->content->jid)
                    )
                );
                $this->rpc('Chats.refresh');
            }
        }
    }

    public function onComposing(array $array)
    {
        $this->setState($array[0], true);
    }

    public function onPaused(array $array)
    {
        $this->setState($array[0], false);
    }

    private function setState(string $jid, bool $composing)
    {
        $chats = \App\Cache::c('chats');
        if (is_array($chats) &&  array_key_exists($jid, $chats)) {
            $this->rpc(
                $composing
                    ? 'MovimUtils.addClass'
                    : 'MovimUtils.removeClass',
                '#' . cleanupId($jid.'_chat_item') . ' span.primary',
                'composing'
            );
        }
    }

    public function ajaxGet()
    {
        $this->rpc('MovimTpl.fill', '#chats_widget_list', $this->prepareChats());
        $this->rpc('Chats.refresh');
    }

    /**
     * @brief Get history
     */
    public function ajaxGetHistory($jid = false)
    {
        $g = new \Moxl\Xec\Action\MAM\Get;

        if ($jid == false) {
            $message = $this->user->messages()
                                  ->orderBy('published', 'desc')
                                  ->first();
            if ($message) {
                $g->setStart(strtotime($message->published));
            }

            $g->setLimit(150);
            $g->request();
        } elseif ($this->validateJid($jid)) {
            $message = $this->user->messages()
                                  ->where(function ($query) use ($jid) {
                                      $query->where('jidfrom', $jid)
                                            ->orWhere('jidto', $jid);
                                  })
                                  ->orderBy('published', 'desc')
                                  ->first();
            $g->setJid(echapJid($jid));

            if ($message) {
                $g->setStart(strtotime($message->published));
            }

            $g->request();
        }
    }

    public function ajaxOpen($jid, $history = true)
    {
        if (!$this->validateJid($jid)) {
            return;
        }

        $chats = \App\Cache::c('chats');
        if ($chats == null) {
            $chats = [];
        }

        unset($chats[$jid]);

        if (/*!array_key_exists($jid, $chats)
                && */$jid != $this->user->id) {
            $chats[$jid] = 1;

            if ($history) {
                $this->ajaxGetHistory($jid);
            }

            \App\Cache::c('chats', $chats);
            $this->rpc(
                'Chats.prepend',
                $jid,
                $this->prepareChat(
                    $jid,
                    $this->resolveContactFromJid($jid),
                    $this->resolveRosterFromJid($jid)
                )
            );

            $this->rpc('Chats.refresh');
        }
    }

    public function ajaxClose($jid, $closeDiscussion = false)
    {
        $notif = new Notification;
        $notif->ajaxClear('chat|'.$jid);

        if (!$this->validateJid($jid)) {
            return;
        }

        $chats = \App\Cache::c('chats');
        unset($chats[$jid]);
        \App\Cache::c('chats', $chats);

        $this->rpc('MovimTpl.remove', '#' . cleanupId($jid . '_chat_item'));
        $this->rpc('Chat_ajaxClearCounter', $jid);
        $this->rpc('Chats.refresh');

        if ($closeDiscussion) {
            $this->rpc('Chat_ajaxGet');
        }
    }

    public function prepareChats($emptyItems = false)
    {
        $unreads = [];

        // Get the chats with unread messages
        $this->user->messages()
            ->select('jidfrom')
            ->where('seen', false)
            ->where('type', 'chat')
            ->groupBy('jidfrom')
            ->pluck('jidfrom')
            ->each(function ($item) use (&$unreads) {
                $unreads[$item] = 1;
            });

        // Append the open chats
        $chats = \App\Cache::c('chats');

        $chats = array_merge(
            is_array($chats) ? $chats : [],
            $unreads
        );

        $view = $this->tpl();

        if (!isset($chats)) {
            return '';
        }

        $contacts = App\Contact::whereIn('id', array_keys($chats))->get()->keyBy('id');
        foreach (array_keys($chats) as $jid) {
            if (!$contacts->has($jid)) {
                $contacts->put($jid, new App\Contact(['id' => $jid]));
            }
        }

        $view->assign('rosters', $this->user->session->contacts()->whereIn('jid', array_keys($chats))
                                      ->with('presence.capability')->get()->keyBy('jid'));
        $view->assign('contacts', $contacts);
        $view->assign('chats', array_reverse($chats));
        $view->assign('emptyItems', $emptyItems);

        return $view->draw('_chats');
    }

    public function prepareEmptyChat($jid)
    {
        $view = $this->tpl();
        $view->assign('jid', $jid);
        return $view->draw('_chats_empty_item');
    }

    public function prepareChat(string $jid, App\Contact $contact, App\Roster $roster = null, $status = null, $active = false)
    {
        if (!$this->validateJid($jid)) {
            return;
        }

        $view = $this->tpl();

        $view->assign('status', $status);
        $view->assign('contact', $contact);
        $view->assign('roster', $roster);
        $view->assign('active', $active);
        $view->assign('count', $this->user->unreads($jid));

        if ($status == null) {
            $view->assign('message', $this->user->messages()
                ->where(function ($query) use ($jid) {
                    $query->where('jidfrom', $jid)
                        ->orWhere('jidto', $jid);
                })
                ->orderBy('published', 'desc')
                ->first());
        }

        return $view->draw('_chats_item');
    }

    public function resolveContactFromJid($jid)
    {
        $contact = App\Contact::find($jid);
        return $contact ? $contact : new App\Contact(['id' => $jid]);
    }

    public function resolveRosterFromJid($jid)
    {
        return $this->user->session->contacts()->where('jid', $jid)
                    ->with('presence.capability')->first();
    }

    private function validateJid($jid)
    {
        return prepJid($jid);
    }
}
