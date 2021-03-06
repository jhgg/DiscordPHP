<?php

/*
 * This file is apart of the DiscordPHP project.
 *
 * Copyright (c) 2016 David Cole <david@team-reflex.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\WebSockets\Events;

use Discord\Parts\User\Member;
use Discord\WebSockets\Event;

/**
 * Event that is emitted wheh `GUILD_MEMBER_UPDATE` is fired.
 */
class GuildMemberUpdate extends Event
{
    /**
     * {@inheritdoc}
     *
     * @return Member The parsed data.
     */
    public function getData($data, $discord)
    {
        return new Member((array) $data, true);
    }

    /**
     * {@inheritdoc}
     */
    public function updateDiscordInstance($data, $discord)
    {
        foreach ($discord->guilds as $index => $guild) {
            if ($guild->id == $data->guild_id) {
                foreach ($guild->members as $mindex => $member) {
                    if ($member->id == $data->id) {
                        $guild->members->pull($mindex);
                        $guild->members->push($data);

                        break;
                    }
                }

                $discord->guilds->pull($index);
                $discord->guilds->push($guild);

                break;
            }
        }

        return $discord;
    }
}
