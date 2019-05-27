<?php

namespace Moxl\Xec\Payload;

use App\Contact;

class Tune extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $from = explodeJid((string)$parent->attributes()->from)['jid'];

        if (isset($stanza->items->item->tune)
        && $stanza->items->item->tune->count() > 0) {
            $contact = Contact::firstOrNew(['id' => $from]);
            $contact->setTune($stanza);
            $contact->save();

            $this->event('tune', $from);
        }
    }
}
