<?php

namespace Moxl\Xec\Payload;

class OMEMOMessage extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $jid = explodeJid((string)$parent->attributes()->from)['jid'];

        $keys = [];
        foreach ($stanza->header->key as $key) {
            $keys[(string)$key->attributes()->rid] = [
                'key'       => (string)$key,
                'isprekey'  => (bool)$key->attributes()->prekey
            ];
        }

        $this->pack([
            'from'      => $jid[0],
            'sid'       => (string)$stanza->header->attributes()->sid,
            'iv'        => (string)$stanza->header->iv,
            'prekeys'   => $keys,
            'payload'   => (string)$stanza->payload
        ]);
        $this->deliver();
    }
}
