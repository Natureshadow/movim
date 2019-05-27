<?php

namespace App;

use Movim\Model;
use Movim\Picture;
use Movim\Session;

class Presence extends Model
{
    protected $primaryKey = ['session_id', 'jid', 'resource'];
    public $incrementing = false;

    protected $attributes = [
        'session_id'    => SESSION_ID,
        'muc'    => false
    ];

    protected $fillable = [
        'session_id',
        'jid',
        'resource',
        'mucjid'
    ];

    public function roster()
    {
        return $this->hasOne('App\Roster', 'jid', 'jid')
                    ->where('session_id', $this->session_id);
    }

    public function capability()
    {
        return $this->hasOne('App\Capability', 'node', 'node');
    }

    public function contact()
    {
        return $this->hasOne('App\Contact', 'id', 'jid');
    }

    public function getPresencetextAttribute()
    {
        return getPresences()[$this->value];
    }

    public function getPresencekeyAttribute()
    {
        return getPresencesTxt()[$this->value];
    }

    public function getConferencePictureAttribute()
    {
        return (new Picture)->get($this->mucjid, 120);
    }

    public function getRefreshableAttribute()
    {
        if (!$this->avatarhash) {
            return false;
        }

        $jid = ($this->muc)
                ? ($this->mucjid)
                    ? $this->mucjid
                    : $this->jid.'/'.$this->resource
                : $this->jid;

        $contact = \App\Contact::where('avatarhash', (string)$this->avatarhash)->first();

        /*
         * Another contact had the same avatar
         */
        if ($contact
        && $contact->id != $jid
        && $this->muc) {
            $p = new Picture;
            $p->fromKey($contact->id);
            $p->set($jid);

            return false;
        }

        return ($contact) ? false : $jid;
    }

    public static function findByStanza($stanza)
    {
        $jid = explodeJid((string)$stanza->attributes()->from);
        return self::firstOrNew([
            'session_id' => SESSION_ID,
            'jid' => $jid['jid'],
            'resource' => $jid['resource']
        ]);
    }

    public function set($stanza)
    {
        $this->session_id = SESSION_ID;

        $jid = explodeJid((string)$stanza->attributes()->from);

        $this->jid = $jid['jid'];
        $this->resource = $jid['resource'];

        if ($stanza->status && !empty((string)$stanza->status)) {
            $this->status = (string)$stanza->status;
        }

        if ($stanza->c) {
            $this->node = (string)$stanza->c->attributes()->node .
                     '#'. (string)$stanza->c->attributes()->ver;
        }

        $this->priority = ($stanza->priority) ? (int)$stanza->priority : 0;

        if ((string)$stanza->attributes()->type == 'error') {
            $this->value = 6;
        } elseif ((string)$stanza->attributes()->type == 'unavailable'
               || (string)$stanza->attributes()->type == 'unsubscribed') {
            $this->value = 5;
        } elseif ((string)$stanza->show == 'away') {
            $this->value = 2;
        } elseif ((string)$stanza->show == 'dnd') {
            $this->value = 3;
        } elseif ((string)$stanza->show == 'xa') {
            $this->value = 4;
        } else {
            $this->value = 1;
        }

        // Specific XEP
        if ($stanza->x) {
            foreach ($stanza->children() as $name => $c) {
                switch ($c->attributes()->xmlns) {
                    /*case 'jabber:x:signed' :
                        $this->publickey = (string)$c;
                        break;*/
                    case 'http://jabber.org/protocol/muc#user':
                        if (!isset($c->item)) {
                            break;
                        }

                        $session = Session::start();

                        $this->muc = true;

                        /**
                         * If we were trying to connect to that particular MUC
                         * See Moxl\Xec\Action\Presence\Muc
                         */
                        if ($session->get((string)$stanza->attributes()->from)) {
                            $this->mucjid = \App\User::me()->id;
                        } elseif ($c->item->attributes()->jid) {
                            $this->mucjid = explodeJid((string)$c->item->attributes()->jid)['jid'];
                        } else {
                            $this->mucjid = (string)$stanza->attributes()->from;
                        }

                        if ($c->item->attributes()->role) {
                            $this->mucrole = (string)$c->item->attributes()->role;
                        }
                        if ($c->item->attributes()->affiliation) {
                            $this->mucaffiliation = (string)$c->item->attributes()->affiliation;
                        }
                        break;
                    case 'vcard-temp:x:update':
                        $this->avatarhash = (string)$c->photo;
                        break;
                }
            }
        }

        if ($stanza->delay) {
            $this->delay = gmdate(
                'Y-m-d H:i:s',
                strtotime(
                    (string)$stanza->delay->attributes()->stamp
                )
            );
        }

        if ($stanza->query) {
            $this->last = (int)$stanza->query->attributes()->seconds;
        }
    }
}
