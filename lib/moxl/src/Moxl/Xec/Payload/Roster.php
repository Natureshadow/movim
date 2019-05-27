<?php

namespace Moxl\Xec\Payload;

use App\Roster as DBRoster;
use App\User as DBUser;

class Roster extends Payload
{
    public function handle($stanza, $parent = false)
    {
        if ((string)$parent->attributes()->type == 'set') {
            $jid = explodeJid((string)$stanza->item->attributes()->jid)['jid'];

            $contact = DBUser::me()->session->contacts()->where('jid', $jid)->first();

            if ($contact) {
                $contact->delete();
            }

            if ((string)$stanza->item->attributes()->subscription != 'remove') {
                $roster = DBRoster::firstOrNew(['jid' => $jid, 'session_id' => DBUser::me()->session->id]);
                $roster->set($stanza->item);
                $roster->save();
            }

            $this->deliver();
        }
    }
}
