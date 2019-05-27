<?php
/*
 * @file Jingle.php
 *
 * @brief Handle Jingle stanza
 *
 * Copyright 2012 edhelas <edhelas@edhelas-laptop>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 *
 *
 */

namespace Moxl\Xec\Payload;

use Moxl\Xec\Action\Ack\Send;

class Jingle extends Payload
{
    public function handle($stanza, $parent = false)
    {
        $from = (string)$parent->attributes()->from;
        $to   = (string)$parent->attributes()->to;
        $id   = (string)$parent->attributes()->id;

        $action = (string)$stanza->attributes()->action;

        $ack = new Send;
        $ack->setTo($from)
            ->setId($id)
            ->request();

        $userid = \App\User::me()->id;
        $message = new \App\Message;
        $message->user_id = $userid;
        $message->id = 'm_' . generateUUID();
        $message->jidto = $userid;
        $message->jidfrom = explodeJid((string)$from)['jid'];
        $message->published = gmdate('Y-m-d H:i:s');
        $message->thread = (string)$stanza->attributes()->sid;

        switch ($action) {
            case 'session-initiate':
                $message->type = 'jingle_start';
                $message->save();
                $this->event('jingle_sessioninitiate', [$stanza, $from]);
                break;
            case 'transport-info':
                $this->event('jingle_transportinfo', $stanza);
                break;
            case 'session-terminate':
                $message->type = 'jingle_end';
                $message->save();
                $this->event('jingle_sessionterminate', $stanza);
                break;
            case 'session-accept':
                $message->type = 'jingle_start';
                $message->save();
                $this->event('jingle_sessionaccept', $stanza);
                break;
        }
    }
}
