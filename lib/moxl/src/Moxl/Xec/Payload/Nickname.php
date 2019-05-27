<?php

namespace Moxl\Xec\Payload;

use App\Contact;

class Nickname extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $from = explodeJid((string)$parent->attributes()->from)['jid'];

        if ($stanza->items->item->nick) {
            $contact = Contact::firstOrNew(['id' => $from]);
            $contact->nickname = (string)$stanza->items->item->nick;
            $contact->save();
        }
    }
}
