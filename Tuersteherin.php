#!/usr/bin/env php
# vim: set et:ts=4:sw=4
<?php
include("SmartIRC.php");

define("IRC_BOLD", "\002");
define("IRC_ITALIC", "\026");
define("IRC_NORMAL", "\017");
define("IRC_UNDERLINE", "\037");

class Tuersteherin {

    private $SmartIRC;

    const Nickname = "Phai";
    const Realname = "Botviech 2.3 Beta";

    const Server = "irc.rizon.net";
    const Port = 6667;
    const Channels = "#winhistory";

    const IdleTimeout = 600;

    private $LoggedIn = array();
    private $idleTime = array();
    private $UserUIDs = array();
    //private $simpleKeywords = array();
    private $previousQuestion = array();

    private $searchEngines = array(
        '!google' => 'https://www.google.de/search?q=',
        '!googlepic' => 'https://www.google.de/search?tbm=isch&q=',
        '!lmgtfy' => 'http://lmgtfy.com/?q=',
        '!wikipedia' => 'https://de.wikipedia.org/w/index.php?title=Spezial:Suche&search=',
        '!wolfram' => 'https://www.wolframalpha.com/input/?i=',
        '!youtube' => 'https://www.youtube.com/results?search_query=',
        '!twitter' => 'https://twitter.com/search?f=realtime&q=',
    );

    function Tuersteherin() {
        $irc = $this->SmartIRC = &new Net_SmartIRC();
        $irc->setUseSockets(true);
        $irc->setChannelSyncing(true);
        $irc->setUserSyncing(true);
        $irc->setAutoReconnect(true);

        $irc->setDebug(SMARTIRC_DEBUG_IRCMESSAGES);

        setlocale(LC_ALL, 'de_DE');
        date_default_timezone_set('Europe/Berlin');

        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '.*', $this, 'updateIdle');
        $irc->registerActionhandler(SMARTIRC_TYPE_NICKCHANGE, '.*', $this, 'updateUUID');
        $irc->registerActionhandler(SMARTIRC_TYPE_WHO, '.*', $this, 'setUUID');
        $irc->registerActionhandler(SMARTIRC_TYPE_QUIT, '.*', $this, 'removeUUID');

        $irc->registerActionhandler(SMARTIRC_TYPE_QUERY, '^!login', $this, 'login');
        $irc->registerActionhandler(SMARTIRC_TYPE_QUERY, '^!logout$', $this, 'logout');
        $irc->registerActionhandler(SMARTIRC_TYPE_QUERY, '^!admins$', $this, 'admins');
        $irc->registerActionhandler(SMARTIRC_TYPE_QUIT, '.*', $this, 'logout');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!kick\s.+', $this, 'kick');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!kickban\s.+', $this, 'kickban');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!whois\s.+', $this, 'whois');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!quit$', $this, 'quit');

        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/^(hallo|huhu|hi)\s'.self::Nickname.'/i', $this, 'Huhu');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!toblerone(\s|$)', $this, 'Toblerone');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!decide[1]?(\s|$)', $this, 'EightBall');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!select[1]?(\s|$)', $this, 'select');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!say\s', $this, 'Say');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!sayme\s', $this, 'SayMe');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!popp\s', $this, 'Popp');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!dice(\s\d|$)', $this, 'Dice');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!?ping(\?|!|\.)?$', $this, 'Ping');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!password', $this, 'PasswordReminder');

        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?chf/i', $this, 'CHFtoEUR');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?euro2kaffee/i', $this, 'euro2kaffee');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?euro2hitler/i', $this, 'euro2hitler');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?euro2mate/i', $this, 'euro2mate');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?chf2mate/i', $this, 'chf2mate');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/[-+]?[0-9]*[.,]?[0-9]+\s?kaffeetassen/i', $this, 'kaffeetassen');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^!(time|date)(\s|$)', $this, 'Time');

        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '/(https?:\/\/([-\w\.]+)+(:\d+)?(\/([\w\/_\-\.]*(\?\S+)?)?)?)/', $this, 'grepURLTitle');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, 'https?:\/\/(www\.)?youtube\.com\/watch\?v=([\w\_\-]+)', $this, 'printYTInfo');

        // without any keywords, we're much better off without this
        //$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '.*', $this, 'simpleKeywords');
        $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '!.+\s', $this, 'searchEngine');

        $irc->connect(self::Server, self::Port);
        $irc->login(self::Nickname, self::Realname, 0, self::Nickname);

        $this->LateLogin = $irc->registerTimehandler(5000, $this, 'lateLogin');

        if(self::IdleTimeout > 0) {
            $irc->registerTimehandler(5000, $this, 'checkIdle');
        }

        $irc->listen();
        $irc->disconnect();
    }

    function updateIdle(&$irc, &$ircdata) {
        $uuid = $this->createUUID($ircdata);

        if(!isset($this->UserUIDs[$uuid])) {
            $this->UserUIDs[$uuid] = $ircdata->nick;
        }

        if(!isset($this->idleTime[$ircdata->channel][$uuid])) {
            for($i=0;$i<4;$i++) {
                $this->idleTime[$ircdata->channel][$uuid][$i] = 0;
            }
        } else {
            for($i=3;$i>0;$i--) {
                $this->idleTime[$ircdata->channel][$uuid][$i] =
                    $this->idleTime[$ircdata->channel][$uuid][$i-1];
            }
        }
        $timestamps = &$this->idleTime[$ircdata->channel][$uuid];
        $timestamps[0] = microtime(true);

        # I personally don't want every user to be autovoiced.
        /* if(self::IdleTimeout != 0 && !$irc->isVoiced($ircdata->channel, $ircdata->nick)) {
            $irc->voice($ircdata->channel, $ircdata->nick);
        }*/

        if($timestamps[0] - $timestamps[3] < 2) {
            $irc->kick($ircdata->channel, $ircdata->nick, "Fluten des Kanals verboten!");
        }
    }

    function lateLogin(&$irc) {
        if(is_readable("Nickserv.txt")) {
            $passwd = trim(file_get_contents("Nickserv.txt"));
            $irc->message(SMARTIRC_TYPE_QUERY, "NickServ", 'GHOST '.self::Nickname.' '.$passwd, SMARTIRC_HIGH);
            $irc->changeNick(self::Nickname, SMARTIRC_HIGH); /* just to be sure */
            $irc->message(SMARTIRC_TYPE_QUERY, "NickServ", 'IDENTIFY '.$passwd, SMARTIRC_HIGH);
        }

        $irc->join(explode('|', self::Channels));

        $irc->unregisterTimeID($this->LateLogin);
        unset($this->LateLogin);
    }

    function checkIdle(&$irc) {
        foreach(explode('|', self::Channels) as $channel) {
            if(!$irc->isJoined($channel)) continue;
            $idleList = array();
            foreach($irc->getChannel($channel)->voices as $user=>$isVoiced) {
                $uuid = $this->getUUID($user);
                $timestamp = isset($this->idleTime[$channel][$uuid]) ?
                                $this->idleTime[$channel][$uuid][0] : 0;

                if(microtime(true) - $timestamp > self::IdleTimeout) {
                    $idleList[] = $user;
                }
            }

            # -v them if idling. Since they don't have +v (in my chan), it's quite pointless.
            if(count($idleList) > 0) {
                # $irc->mode($channel, '-'.str_repeat('v', count($idleList)).' '.implode(' ', $idleList));
            }
        }
    }

    function updateUUID(&$irc, &$ircdata) {
        $uuid = $this->createUUID($ircdata);
        $this->UserUIDs[$uuid] = $ircdata->message;
    }

    function setUUID(&$irc, &$ircdata) {
        $raw_msg = &$ircdata->rawmessageex;
        if($raw_msg[1] == SMARTIRC_RPL_WHOREPLY) {
            $uuid = $raw_msg[4].'@'.$raw_msg[5];
            $this->UserUIDs[$uuid] = $raw_msg[7];
        }
    }

    function removeUUID(&$irc, &$ircdata) {
        $uuid = $this->createUUID($ircdata);
        unset($this->UserUIDs[$uuid]);
        foreach($this->idleTime as $channel=>$user) {
            unset($this->idleTime[$channel][$uuid]);
        }
    }

    function getNickname($uuid) {
        return $this->UserUIDs[$uuid];
    }

    function getUUID($nickname) {
        return array_search($nickname, $this->UserUIDs);
    }

    function createUUID(&$ircdata) {
        return $ircdata->ident.'@'.$ircdata->host;
    }

    function quit(&$irc, &$ircdata) {
        if($this->checkLogin($ircdata->nick)) {
            $irc->quit("Heil Diskordia!");
        }
    }

    function whois(&$irc, &$ircdata) {
        $uuid = $this->getUUID($ircdata->messageex[1]);
        if($irc->isMe($ircdata->messageex[1])) {
            $msg = "Das bin ich selber.";
        } elseif($uuid === false) {
            $msg = "Fehler: Unbekannter Benutzer";
        } else {
            $msg = $uuid;
        }
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function kickban(&$irc, &$ircdata) {
        $this->kick($irc, $ircdata, true);
    }

    function kick(&$irc, &$ircdata, $kickban = false) {
        if($irc->isOpped($ircdata->channel)) {
            $user = $ircdata->messageex[1];
            if($this->checkLogin($ircdata->nick)) {
                if($kickban && ($uuid = $this->getUUID($user)) !== false) {
                    $irc->ban($ircdata->channel, '*!'.$uuid);
                }
                $irc->kick($ircdata->channel, $user, "WUTSCHNAUBZETTER");
            } else {
                $irc->kick($ircdata->channel, $ircdata->nick, "Du hast mir gar nichts zu sagen.");
            }
        }
    }

    function login(&$irc, &$ircdata) {
        if(!isset($ircdata->messageex[2])) return;
        $user = $ircdata->messageex[1];
        $pass = $ircdata->messageex[2];
        $Admins = parse_ini_file("Admins.txt");

        if(isset($Admins[$user]) && sha1($pass) == $Admins[$user]) {
            $uuid = $this->createUUID($ircdata);
            $this->LoggedIn[$uuid] = true;
            $irc->message(SMARTIRC_TYPE_QUERY, $ircdata->nick, "Logged in");
        }
    }

    function checkLogin($nick) {
        $uuid = $this->getUUID($nick);
        return isset($this->LoggedIn[$uuid]) ? $this->LoggedIn[$uuid] : false;
    }

    function admins(&$irc, &$ircdata) {
        if(!$this->checkLogin($ircdata->nick)) return;
        $irc->message(SMARTIRC_TYPE_QUERY, $ircdata->nick, "Logged in admins:");
        foreach($this->LoggedIn as $uuid=>$loggedin) {
            if($loggedin) {
                $irc->message(SMARTIRC_TYPE_QUERY, $ircdata->nick, $this->getNickname($uuid));
            }
        }
    }

    function logout(&$irc, &$ircdata) { //FIXME funktioniert nicht bei quit
        if($this->checkLogin($ircdata->nick)) {
            unset($this->LoggedIn[$ircdata->nick]);
            $irc->message(SMARTIRC_TYPE_QUERY, $ircdata->nick, "Logged out");
        }
    }

    function Huhu(&$irc, &$ircdata) {
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $ircdata->messageex[0].' '.$ircdata->nick);
    }

    function Toblerone(&$irc, &$ircdata) {
        $nick = $this->_message_line($ircdata->message, $ircdata->nick);
        $irc->message(SMARTIRC_TYPE_ACTION, $ircdata->channel, 'gibt '.$nick.' eine Toblerone!');
    }

    function Say(&$irc, &$ircdata) {
        $message = $this->_message_line($ircdata->message);
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $message);
    }

    function SayMe(&$irc, &$ircdata) {
        $message = $this->_message_line($ircdata->message);
        $irc->message(SMARTIRC_TYPE_ACTION, $ircdata->channel, $message);
    }

    function Popp(&$irc, &$ircdata) {
        $nick = $this->_message_line($ircdata->message);
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, '*'.$nick.' anpopp* :o');
    }

    function Dice(&$irc, &$ircdata) {
        $max = isset($ircdata->messageex[1]) ? $ircdata->messageex[1] : 6;
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, '*wuerfel*');
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, mt_rand(1, $max));
    }

    function grepURLTitle(&$irc, &$ircdata) {
        preg_match("@(?<url>https?://([-\w\.]+)+(:\d+)?(/([\w/_\-\.]*(\?\S+)?)?)?)@", $ircdata->message, $url);
        $url = $url['url'];
        // let printYTInfo handle that
        if (preg_match("/https?:\/\/(www\.)?youtube\.com\/watch\?/", $url)) return;

        if(!($httpSocket = @fopen($url, 'r'))) {
            return;
        }
        stream_set_timeout($httpSocket, 1);
        $data = stream_get_contents($httpSocket, 2048);

        if(preg_match("/\<title\>(?<header>.*)\<\/title\>/i", $data, $tags)) {
            $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, 'Link-Titel: '.IRC_UNDERLINE.html_entity_decode($tags['header']));
        }
        fclose($httpSocket);
    }

        function select(&$irc, &$ircdata) {
                $question = $this->_message_line($ircdata->message);
        $qa = explode(" oder ", $this->_message_line($ircdata->message));
                $answer = IRC_BOLD.$qa[mt_rand(0, count($qa)-1)];
        $msg = '<'.$ircdata->nick.'>'." ".$answer;
                $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function EightBall(&$irc, &$ircdata) {
        $answers = array(
            'Ja.',
            'Nein.'
        );
        $insults = array(
            'Ruhe.',
            'Siehe oben.'
        );
        //if($ircdata->messageex[0] == "!8ball" && rand(0, 3) == 0 && $irc->isOpped($ircdata->channel)) {
        //    $irc->kick($ircdata->channel, $ircdata->nick, ':o');
        //} else {
        $question = $this->_message_line($ircdata->message);
        if (array_key_exists($ircdata->nick, $this->previousQuestion) && $this->previousQuestion[$ircdata->nick] == $question) {
            $answer = IRC_BOLD.$insults[mt_rand(0, count($insults)-1)];
        }
        else {
            $answer = IRC_BOLD.$answers[mt_rand(0, count($answers)-1)];
        }
        $msg = '<'.$ircdata->nick.'>'.(empty($question)?'':' '.$question).' '.$answer;
        if (!empty($question)) $this->previousQuestion[$ircdata->nick] = $question;
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
        //}
    }

    function Ping(&$irc, &$ircdata) {
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, 'Pong!');
    }

    function Time(&$irc, &$ircdata) {
        $irc->message(
            SMARTIRC_TYPE_CHANNEL, $ircdata->channel,
            "Man erzählt sich, es sei ".date("l, d. F, H:i:s")." Uhr"
        );
    }

    function simpleKeywords(&$irc, &$ircdata) {
        foreach($this->simpleKeywords as $keyword => $answer) {
            if(preg_match('/(^|\b)'.$keyword.'(\b|$)/i', $ircdata->message)) {
                $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $answer);
            }
        }
    }

    function searchEngine(&$irc, &$ircdata) {
        if(isset($this->searchEngines[''.$ircdata->messageex[0]])) {
            $query = $this->searchEngines[''.$ircdata->messageex[0]].
                                urlencode($this->_message_line($ircdata->message));
            $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, '-> '.$query);
        }
    }

    function CHFtoEUR(&$irc, &$ircdata) {
        static $convrate = 0.81;
        static $lastupdate = -1;
        static $exactrate = FALSE;

        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?chf/i", $ircdata->message, $value);
        $chf = strtr($value['value'], ',', '.');

        if (time() - $lastupdate >= 86400 || !$exactrate) {
            $context = stream_context_create(array('http' => array('timeout' => 1)));
            $input = file_get_contents('http://rate-exchange.appspot.com/currency?from=CHF&to=EUR', 0, $context);
            $json = json_decode($input, TRUE);
            if ($json && empty($json['err'])) {
                $convrate = $json['rate'];
                $lastupdate = time();
                $exactrate = TRUE;
            } else {
                // better an approximated conversation rate than an outdated one
                $convrate = 0.81;
                $exactrate = FALSE;
            }
        }
        $eur = $chf * $convrate;
        if($exactrate) {
            if ($chf == 1) {
                $msg = $chf.' CHF ist exakt '.number_format($eur, 2, ',', '.').' EUR';
            } else {
                $msg = $chf.' CHF sind exakt '.number_format($eur, 2, ',', '.').' EUR';
            }
        } else {
            if ($chf == 1) {
                $msg = $chf.' CHF ist ungefähr '.number_format($eur, 2, ',', '.').' EUR';
            } else {
                $msg = $chf.' CHF sind ungefähr '.number_format($eur, 2, ',', '.').' EUR';
            }
        }
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

     function euro2hitler(&$irc, &$ircdata) {
        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?euro2hitler/i", $ircdata->message, $value);
        $input = strtr($value['value'], ',', '.');

        if($input <= 4) {
            $hitler = $input * 1000 / 80000000 / 5000000 / 0.000000000001;
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($hitler, 2, ',', '.').' FemtoHitler';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($hitler, 2, ',', '.').' FemtoHitler';
            }
        } else {
            $hitler = $input / 80000000 / 5000000 / 0.000000000001;
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($hitler, 2, ',', '.').' PicoHitler';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($hitler, 2, ',', '.').' PicoHitler';
            }
        }

        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function euro2mate(&$irc, &$ircdata) {
        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?euro2mate/i", $ircdata->message, $value);
        $input = strtr($value['value'], ',', '.');
        $mate = $input / 0.84;

        if($mate == 1) {
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($mate, 2, ',', '.').' Flasche Mate';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($mate, 2, ',', '.').' Flasche Mate';
            }
        } else {
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($mate, 2, ',', '.').' Flaschen Mate';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($mate, 2, ',', '.').' Flaschen Mate';
            }
        }

        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function chf2mate(&$irc, &$ircdata) {
        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?euro2mate/i", $ircdata->message, $value);
        $input = strtr($value['value'], ',', '.');
        $mate = $input / 2.2;

        if($mate == 1) {
            if($input == 1) {
                $msg = $input.' CHF ist exakt '.number_format($mate, 2, ',', '.').' Flasche Mate';
            } else {
                $msg = $input.' CHF sind exakt '.number_format($mate, 2, ',', '.').' Flasche Mate';
            }
        } else {
            if($input == 1) {
                $msg = $input.' CHF ist exakt '.number_format($mate, 2, ',', '.').' Flaschen Mate';
            } else {
                $msg = $input.' CHF sind exakt '.number_format($mate, 2, ',', '.').' Flaschen Mate';
            }
        }

        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function kaffeetassen(&$irc, &$ircdata) {
        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?kaffeetassen/i", $ircdata->message, $value);
        $input = strtr($value['value'], ',', '.');
        $eur = $input * 3.36;

        if($input == 1) {
            $msg = $input.' Kaffeetasse ist exakt '.number_format($eur, 2, ',', '.').' Euro';
        } else {
            $msg = $input.' Kaffeetassen sind exakt '.number_format($eur, 2, ',', '.').' EUR';
        }

        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function euro2kaffee(&$irc, &$ircdata) {
        preg_match("/(?<value>[-+]?[0-9]*[.,]?[0-9]+)\s?eur2kaffee/i", $ircdata->message, $value);
        $input = strtr($value['value'], ',', '.');
        $kaffee = $input / 3.36;

        if($kaffee == 1) {
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($kaffee, 2, ',', '.').' Tasse Kaffee';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($kaffee, 2, ',', '.').' Tasse Kaffee';
            }
        } else {
            if($input == 1) {
                $msg = $input.' Euro ist exakt '.number_format($mate, 2, ',', '.').' Tassen Kaffee';
            } else {
                $msg = $input.' Euro sind exakt '.number_format($mate, 2, ',', '.').' Tassen Kaffee';
            }
        }

        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function printYTInfo(&$irc, &$ircdata) {
        preg_match('/https?:\/\/(www\.)?youtube\.com\/watch\?v=(?<id>[\w\_\-]+)/', $ircdata->message, $yt);
        $sxml = simplexml_load_file('http://gdata.youtube.com/feeds/api/videos/'.$yt['id']);
        if($sxml === false) return;
        $title = $sxml->title;
        $author = $sxml->author->name;

        // epic re-use :P
        $duration = $sxml->children('http://search.yahoo.com/mrss/');
        $duration = $duration->children('http://gdata.youtube.com/schemas/2007');
        $duration = $duration->duration->attributes()->seconds;

        $min = (int) ($duration / 60);
        $sec = sprintf("%02d", $duration % 60);

        $msg = IRC_BOLD."Titel: ".IRC_NORMAL.$title." | ".
               IRC_BOLD."Länge: ".IRC_NORMAL.$min.':'.$sec." | ".
               IRC_BOLD."Uploader: ".IRC_NORMAL.$author;
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function PasswordReminder(&$irc, &$ircdata) {
        // Every randomizer script needs a good old-fashioned placeholder. -JK
        $answers = array(
            'Wie lautet FLASH_SENTRYs Passwort?',
            'Was war FLASH_SENTRYs Passwort gleich noch mal?',
            'Das tolle an Passwörtern ist, dass FLASH_SENTRY sie ständig vergisst.',
            'Ha! FLASH_SENTRY hat schon wieder sein Passwort vergessen!'
        );
        $msg = str_replace('FLASH_SENTRY', $ircdata->nick, $answers[mt_rand(0, count($answers) - 1)]);
        $irc->message(SMARTIRC_TYPE_CHANNEL, $ircdata->channel, $msg);
    }

    function _message_line($message, $fallback = '') {
        @list($cmd, $line) = explode(' ', $message, 2);
        $line = (is_null($line)) ? $fallback : $line;
        return $line;
    }

}

$T = new Tuersteherin();

?>
