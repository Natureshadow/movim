<?php

namespace Moxl\Xec\Payload;

use Movim\ChatStates;
use App\Reaction;

class Message extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $from = (string)$stanza->attributes()->type == 'groupchat'
            ? (string)$stanza->attributes()->from
            : explodeJid((string)$stanza->attributes()->from)['jid'];
        $to = explodeJid((string)$stanza->attributes()->to)['jid'];

        if ($stanza->confirm
        && $stanza->confirm->attributes()->xmlns == 'http://jabber.org/protocol/http-auth') {
            return;
        }

        if ($stanza->attributes()->type == 'error') {
            return;
        }

        if ($stanza->composing) {
            (ChatStates::getInstance())->composing($from, $to);
        }

        if ($stanza->paused) {
            (ChatStates::getInstance())->paused($from, $to);
        }

        $message = \App\Message::findByStanza($stanza);
        $message = $message->set($stanza, $parent);

        if (!$message->isOTR()
        && (!$message->isEmpty() || $message->isSubject())) {
            $message->save();
            $message = $message->fresh();

            if ($message->body || $message->subject) {
                $this->pack($message);
                $this->deliver();
            }
        }
    }
}
