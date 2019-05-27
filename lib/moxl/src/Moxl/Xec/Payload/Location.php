<?php

namespace Moxl\Xec\Payload;

use App\Contact;

class Location extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $from = explodeJid((string)$parent->attributes()->from)['jid'];

        if (isset($stanza->items->item->geoloc)
        && $stanza->items->item->geoloc->count() > 0) {
            $contact = Contact::firstOrNew(['id' => $from]);
            $contact->setLocation($stanza);
            $contact->save();
        }
    }
}
