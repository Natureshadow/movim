<?php

use Movim\Route;
use App\Configuration;

use Znerol\Component\Stringprep\Profile\Nodeprep;
use Znerol\Component\Stringprep\Profile\Nameprep;
use Znerol\Component\Stringprep\Profile\Resourceprep;
use Znerol\Component\Stringprep\ProfileException;

function addUrls($string, bool $preview = false)
{
    // Add missing links
    return preg_replace_callback(
        "/<a[^>]*>[^<]*<\/a|\".*?\"|((?i)\b((?:[a-z][\w-]+:(?:\/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’])))/",
        function ($match) use ($preview) {
            if (isset($match[1])) {
                $content = $match[1];

                $lastTag = false;
                if (in_array(substr($content, -3, 3), ['&lt', '&gt'])) {
                    $lastTag = substr($content, -3, 3);
                    $content = substr($content, 0, -3);
                }

                if ($preview) {
                    try {
                        $embed = Embed\Embed::create($match[0]);
                        if ($embed->type == 'photo'
                        && $embed->images[0]['width'] <= 1024
                        && $embed->images[0]['height'] <= 1024) {
                            $content = '<img src="'.$match[0].'"/>';
                        } elseif ($embed->type == 'link') {
                            $content .= ' - '. $embed->title . ' - ' . $embed->providerName;
                        }
                    } catch (Exception $e) {
                        error_log($e->getMessage());
                    }
                }

                if (substr($content, 0, 5) == 'xmpp:') {
                    $link = str_replace(['xmpp://', 'xmpp:'], '', $content);

                    if (substr($link, -5, 5) == '?join') {
                        return stripslashes(
                            '<a href=\"'.
                            Route::urlize('chat', [str_replace('?join', '', $link), 'room']).
                            '\">'.
                            $content.
                            '</a>'
                        );
                    }
                    return stripslashes(
                        '<a href=\"'.
                        Route::urlize('contact', $link).
                        '\">'.
                        $content.
                        '</a>'
                    );
                }

                if (in_array(parse_url($content, PHP_URL_SCHEME), ['http', 'https'])) {
                    return stripslashes('<a href=\"'.$content.'\" target=\"_blank\" rel=\"noopener\">'.$content.'</a>').
                            ($lastTag !== false ? $lastTag : '');
                }

                return $content;
            }
            return $match[0];
        },
        $string
    );
}

function addHashtagsLinks($string)
{
    return preg_replace_callback("/([\n\r\s>]|^)#(\w+)/u", function ($match) {
        return
            $match[1].
            '<a class="innertag" href="'.\Movim\Route::urlize('tag', $match[2]).'">'.
            '#'.$match[2].
            '</a>';
    }, $string);
}

function addHFR($string)
{
    // HFR EasterEgg
    return preg_replace_callback(
        '/\[:([\w\s-]+)([:\d])*\]/',
        function ($match) {
            $num = '';
            if (count($match) == 3) {
                $num = $match[2].'/';
            }
            return '<img class="hfr" title="'.$match[0].'" alt="'.$match[0].'" src="http://forum-images.hardware.fr/images/perso/'.$num.$match[1].'.gif"/>';
        },
        $string
    );
}

function addEmojis($string, bool $noTitle = false)
{
    $emoji = \Movim\Emoji::getInstance();
    return $emoji->replace($string, $noTitle);
}

/**
 * Prepare the string (add the a to the links and show the smileys)
 */
function prepareString($string, bool $preview = false)
{
    return addEmojis(addUrls($string, $preview));
}

/**
 * Return the tags in a string
 */
function getHashtags($string): array
{
    $hashtags = [];
    preg_match_all("/(#\w+)/u", $string, $matches);

    if ($matches) {
        $hashtagsArray = array_count_values($matches[0]);
        $hashtags = array_map(function ($tag) {
            return substr($tag, 1);
        }, array_keys($hashtagsArray));
    }

    return $hashtags;
}

/*
 * Echap the JID
 */
function echapJid($jid): string
{
    $jidParts = explodeJid($jid);

    $from = array('\\',   ' ',    '"',    '&',    '\'',   '/',    ':',    '<',    '>',    '@');
    $to =   array('\\5c', '\\20', '\\22', '\\26', '\\27', '\\2f', '\\3a', '\\3c', '\\3e', '\\40');

    $jidParts['username'] = str_replace($from, $to, $jidParts["username"]);

    return implodeJid($jidParts);
}

/*
 * Echap the anti-slashs for Javascript
 */
function echapJS($string): string
{
    return str_replace(["\\", "'"], ["\\\\", "\\'"], $string);
}

/**
 * Extract the CID
 */
function getCid($string)
{
    preg_match("/(\w+)\@/", $string, $matches);
    if (is_array($matches)) {
        return $matches[1];
    }
}

/**
 * Explose query parameters into an array
 */
function explodeQueryParams(string $query): array
{
    $params = [];

    foreach (explode(';', $query) as $param) {
        $result = explode('=', $param);
        if (count($result) == 2) {
            $params[$result[0]] = $result[1];
        }
    }

    return $params;
}

/**
 *  Explode JID
 */
function explodeJid($jid): array
{
    if ($jid) {
        // Split local part out if it exists
        if (strpos($jid, '@') !== false) {
            $splits = explode('@', $jid, 2);
            $local = $splits[0];
            $rest = $splits[1];
        } else {
            $local = '';
            $rest = $jid;
        }

        // Split resource part out if it exists
        if (strpos($rest, '/') !== false) {
            $splits = explode('/', $rest, 2);
            $resource = $splits[1];
            $domain = $splits[0];
        } else {
            $resource = '';
            $domain = $rest;
        }
    } else {
        $local = '';
        $domain = '';
        $resource = '';
    }

    return [
        'username'  => $local,
        'server'    => $domain,
        'jid'       => $local.'@'.$domain,
        'resource'  => $resource
    ];
}

/**
 *  Implode JID
 */
function implodeJid(array $jid): string
{
    return ($jid['username'] !== '' ?
                $jid['username'] . '@' :
                '') .
           $jid['server'] .
           ($jid['resource'] !== '' ?
               '/' . $jid['resource'] :
               '');
}

/**
 * Return a human readable filesize
 */
function sizeToCleanSize($bytes, int $precision = 2): string
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);

    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Return a colored string in the console
 */
function colorize($string, string $color): string
{
    $colors = [
        'black'     => 30,
        'red'       => 31,
        'green'     => 32,
        'yellow'    => 33,
        'blue'      => 34,
        'purple'    => 35,
        'turquoise' => 36,
        'white'     => 37
    ];

    return "\033[" . $colors[$color] . "m" . $string . "\033[0m";
}

/**
 * Check if the mimetype is a picture
 */
function typeIsPicture(string $type): bool
{
    return in_array($type, ['image/jpeg', 'image/png', 'image/jpg', 'image/gif']);
}

/**
 * Check if the mimetype is an audio file
 */
function typeIsAudio(string $type): bool
{
    return in_array(
        $type,
        [
        'audio/aac', 'audio/ogg', 'video/ogg', 'audio/opus',
        'audio/vorbis', 'audio/speex', 'audio/mpeg']
    );
}

/**
 * Return a color generated from the string
 */
function stringToColor($string): string
{
    $colors = [
        0 => 'red',
        1 => 'purple',
        2 => 'indigo',
        3 => 'blue',
        4 => 'green',
        5 => 'orange',
        6 => 'yellow',
        7 => 'brown',
        8 => 'lime',
        9 => 'cyan',
        10 => 'teal',
        11 => 'pink',
        12 => 'dorange',
        13 => 'lblue',
        14 => 'amber',
        15 => 'bgray',
    ];

    $s = abs(crc32($string));
    return $colors[$s%16];
}

/**
 * Strip tags and add a whitespace
 */
function stripTags($string): string
{
    return strip_tags(preg_replace('/(<\/[^>]+?>)(<[^>\/][^>]*?>)/', '$1 $2', $string));
}

/**
 * Purify a string
 */
function purifyHTML($string): string
{
    $config = \HTMLPurifier_Config::createDefault();
    $config->set('HTML.Doctype', 'XHTML 1.1');
    $config->set('Cache.SerializerPath', '/tmp');
    $config->set('HTML.DefinitionID', 'html5-definitions');
    $config->set('HTML.DefinitionRev', 1);
    $config->set('CSS.AllowedProperties', ['float']);
    if ($def = $config->maybeGetRawHTMLDefinition()) {
        $def->addElement('video', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
          'src' => 'URI',
          'type' => 'Text',
          'width' => 'Length',
          'height' => 'Length',
          'poster' => 'URI',
          'preload' => 'Enum#auto,metadata,none',
          'controls' => 'Bool',
        ]);
        $def->addElement('audio', 'Block', 'Optional: (source, Flow) | (Flow, source) | Flow', 'Common', [
          'src' => 'URI',
          'preload' => 'Enum#auto,metadata,none',
          'muted' => 'Bool',
          'controls' => 'Bool',
        ]);
        $def->addElement('source', 'Block', 'Flow', 'Common', [
          'src' => 'URI',
          'type' => 'Text',
        ]);
    }

    $purifier = new \HTMLPurifier($config);
    $trimmed = trim($purifier->purify($string));
    return preg_replace('#(\s*<br\s*/?>)*\s*$#i', '', $trimmed);
}

/**
 * Check if a string is RTL
 */
function isRTL($string): bool
{
    return preg_match('/\p{Arabic}|\p{Hebrew}/u', $string);
}

/**
 * Invert a number
 */
function invertSign($num)
{
    return ($num <= 0) ? abs($num) : -$num ;
}

/**
 * Return the first two letters of a string
 */
function firstLetterCapitalize($string, bool $firstOnly = false): string
{
    $size = ($firstOnly) ? 1 : 2;
    return mb_convert_case(mb_substr($string, 0, $size), MB_CASE_TITLE);
}

/**
 * Return a clean string that can be used for HTML ids
 */
function cleanupId($string)
{
    return "id-" . strtolower(preg_replace('/([^a-z0-9]+)/i', '-', $string));
}

/**
 * Truncates the given string at the specified length.
 */
function truncate($str, int $width): string
{
    return strtok(wordwrap($str, $width, "…\n"), "\n");
}

/**
 * Return the URI of a path with a timestamp
 */
function urilize($path, bool $noTime = false): string
{
    if ($noTime || !file_exists(PUBLIC_PATH . '/' . $path)) {
        return BASE_URI . $path;
    }

    return BASE_URI . $path . '?t=' . filemtime(PUBLIC_PATH . '/' . $path);
}

/**
 * Return a comma-separated list of joined array elements
 */
function implodeCsv($value) {
    return implode(', ', $value);
}

/**
 * Returns true if the value is an IPv6 or IPv4 address (in that order)
 */
function isIpAddress($value)
{
    $ipV6Regex = '/^\[(?:(?:(?:[0-9A-Fa-f]{0,4}:){7}[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){6}:[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){5}:(?:[0-9A-Fa-f]{0,4}:)?[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){4}:(?:[0-9A-Fa-f]{0,4}:){0,2}[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){3}:(?:[0-9A-Fa-f]{0,4}:){0,3}[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){2}:(?:[0-9A-Fa-f]{0,4}:){0,4}[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){6}(?:(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2}))\.){3}(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2})))|(?:(?:[0-9A-Fa-f]{0,4}:){0,5}:(?:(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2}))\.){3}(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2})))|(?:::(?:[0-9A-Fa-f]{0,4}:){0,5}(?:(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2}))\.){3}(?:(?:25[0-5])|(?:2[0-4]\d)|(?:1\d{2})|(?:\d{1,2})))|(?:[0-9A-Fa-f]{0,4}::(?:[0-9A-Fa-f]{0,4}:){0,5}[0-9A-Fa-f]{0,4})|(?:::(?:[0-9A-Fa-f]{0,4}:){0,6}[0-9A-Fa-f]{0,4})|(?:(?:[0-9A-Fa-f]{0,4}:){1,7}:))\]$/';
    $ipV4Regex = '/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/';

    return (preg_match($ipV6Regex, $value) || preg_match($ipV4Regex, $value));
}

/**
 * Returns a part of a JID, stringprep'ed with the given profile,
 * or false if it is invalid under that profile.
 */
function prepJidPart($value, $prep)
{
    try {
        $ret = $prep->apply($value);
    } catch (ProfileException $e) {
        return false;
    }

    if ((strlen($ret) < 1) || (strlen($ret) > 1023)) {
        return false;
    }

    return $ret;
}

/**
 * Returns a JID, stringprep'ed with the given profile,
 * or false if it is invalid under that profile.
 */
function prepJid($value)
{
    // Split JID into parts
    $jidParts = explodeJid($value);

    // Validate local part against Nodeprep profile
    if ($jidParts['username'] !== '') {
        $jidParts['username'] = prepJidPart($jidParts['username'], new Nodeprep());
        if ($jidParts['username'] === false) {
            return false;
        }
    }

    // Validate resource part against Resourceprep profile
    if ($jidParts['resource'] !== '') {
        $jidParts['resource'] = prepJidPart($jidParts['resource'], new Resourceprep());
        if ($jidParts['resource'] === false) {
            return false;
        }
    }

    // Finally, validate domain part (IPv6, IPv4, FQDN, in that order)
    if (!isIpAddress($jidParts['server'])) {
        // First, strip trailing label delimiter .
        $jidParts['server'] = rtrim($jidParts['server'], '.');

        // Verify that each label is a valid Internationalized Domain Name
        foreach (explode('.', $jidParts['server']) as $label) {
            // Apply UseSTD3ASCIIRules
            if (preg_match('/^-|-$|[\x{00}-\x{2c}\x{2e}-\x{2f}\x{3a}-\x{40}\x{5b}-\x{60}\x{7b}-\x{7f}]/', $label)) {
                return false;
            }

            // Try ToASCII conversion
            if (idn_to_ascii($label, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) === false) {
                return false;
            }
        }

        // Finally, do Nameprep normalisation
        $jidParts['server'] = prepJidPart($jidParts['server'], new Nameprep());
    }

    // Invalid if any part is invalid
    if (($jidParts['username'] === false) || ($jidParts['server'] === false) || ($jidParts['resource'] === false)) {
        return false;
    }

    // Rebuild JID with parts properly stringprep'ed if everything was valid
    return implodeJid($jidParts);
}
