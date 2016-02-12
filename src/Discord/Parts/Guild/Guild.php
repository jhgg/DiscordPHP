<?php

/*
 * This file is apart of the DiscordPHP project.
 *
 * Copyright (c) 2016 David Cole <david@team-reflex.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Parts\Guild;

use Discord\Exceptions\DiscordRequestFailedException;
use Discord\Helpers\Collection;
use Discord\Helpers\Guzzle;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Permissions\RolePermission as Permission;
use Discord\Parts\Part;
use Discord\Parts\User\Member;
use Discord\Parts\User\User;

/**
 * A Guild is Discord's equivalent of a server. It contains all the Members, Channels, Roles, Bans etc.
 */
class Guild extends Part
{
    const REGION_DEFAULT = self::REGION_US_WEST;
    const REGION_US_WEST = 'us-west';
    const REGION_US_EAST = 'us-east';
    const REGION_SINGAPORE = 'singapore';
    const REGION_LONDON = 'london';
    const REGION_SYDNEY = 'sydney';
    const REGION_AMSTERDAM = 'amsterdam';

    const LEVEL_OFF = 0;
    const LEVEL_LOW = 1;
    const LEVEL_MEDIUM = 2;
    const LEVEL_TABLEFLIP = 3;

    /**
     * {@inheritdoc}
     */
    protected $fillable = ['id', 'name', 'icon', 'region', 'owner_id', 'roles', 'joined_at', 'afk_channel_id', 'afk_timeout', 'embed_enabled', 'embed_channel_id', 'features', 'splash', 'emojis', 'large', 'verification_level'];

    /**
     * {@inheritdoc}
     */
    protected $uris = [
        'get' => 'guilds/:id',
        'create' => 'guilds',
        'update' => 'guilds/:id',
        'delete' => 'guilds/:id',
    ];

    /**
     * An array of valid regions.
     *
     * @var array Array of valid regions.
     */
    protected $regions = [
        self::REGION_US_WEST,
        self::REGION_US_EAST,
        self::REGION_LONDON,
        self::REGION_SINGAPORE,
        self::REGION_SYDNEY,
        self::REGION_AMSTERDAM,
    ];

    /**
     * Alias for delete().
     *
     * @return bool Whether the attempt to leave succeeded or failed.
     *
     * @see \Discord\Parts\Part::delete() This function is an alias for delete.
     */
    public function leave()
    {
        return $this->delete();
    }

    /**
     * Transfers ownership of the guild to
     * another member.
     *
     * @param Member|int $member The member to transfer ownership to.
     *
     * @return bool Whether the attempt succeeded or failed.
     */
    public function transferOwnership($member)
    {
        if ($member instanceof Member) {
            $member = $member->id;
        }

        try {
            $request = Guzzle::patch($this->replaceWithVariables('guilds/:id'), [
                'owner_id' => $member,
            ]);

            if ($request->owner_id != $member) {
                return false;
            }

            $this->fill((array) $request);
        } catch (DiscordRequestFailedException $e) {
            return false;
        }

        return true;
    }

    /**
     * Returns the guilds members.
     *
     * @return Collection A collection of members.
     */
    public function getMembersAttribute()
    {
        if (isset($this->attributes_cache['members'])) {
            return $this->attributes_cache['members'];
        }

        // TODO: When this is implemented, it will be paginated.
        $request = Guzzle::get($this->replaceWithVariables('guilds/:id/members'));
        $members = [];

        foreach ($request as $index => $member) {
            $members[$index] = new Member((array) $member, true);
        }

        $this->attributes_cache['members'] = new Collection($members);

        return $this->attributes_cache['members'];
    }

    /**
     * Returns the guilds roles.
     *
     * @return Collection A collection of roles.
     */
    public function getRolesAttribute()
    {
        if (isset($this->attributes_cache['roles'])) {
            return $this->attributes_cache['roles'];
        }

        $roles = [];

        foreach ($this->attributes['roles'] as $index => $role) {
            $perm = new Permission();
            $perm->perms = $role->permissions;
            $role = (array) $role;
            $role['permissions'] = $perm;
            $role['guild_id'] = $this->id;
            $roles[$index] = new Role($role, true);
        }

        $roles = new Collection($roles);

        $this->attributes_cache['roles'] = $roles;

        return $roles;
    }

    /**
     * Returns the owner.
     *
     * @return User An User part.
     */
    public function getOwnerAttribute()
    {
        if (isset($this->attributes_cache['owner'])) {
            return $this->attributes_cache['owner'];
        }

        $request = Guzzle::get($this->replaceWithVariables('users/:owner_id'));

        $owner = new User((array) $request, true);

        $this->attributes_cache['owner'] = $owner;

        return $owner;
    }

    /**
     * Returns the guilds channels.
     *
     * @return Collection A collection of channels.
     */
    public function getChannelsAttribute()
    {
        if (isset($this->attributes_cache['channels'])) {
            return $this->attributes_cache['channels'];
        }

        $channels = [];
        $request = Guzzle::get($this->replaceWithVariables('guilds/:id/channels'));

        foreach ($request as $index => $channel) {
            $channels[$index] = new Channel((array) $channel, true);
        }

        $channels = new Collection($channels);

        $this->attributes_cache['channels'] = $channels;

        return $channels;
    }

    /**
     * Returns the guilds bans.
     *
     * @return Collection A collection of bans.
     */
    public function getBansAttribute()
    {
        if (isset($this->attributes_cache['bans'])) {
            return $this->attributes_cache['bans'];
        }

        $bans = [];

        try {
            $request = Guzzle::get($this->replaceWithVariables('guilds/:id/bans'));
        } catch (DiscordRequestFailedException $e) {
            return false;
        }

        foreach ($request as $index => $ban) {
            $ban = (array) $ban;
            $ban['guild'] = $this;
            $bans[$index] = new Ban($ban, true);
        }

        $bans = new Collection($bans);

        $this->attributes_cache['bans'] = $bans;

        return $bans;
    }

    /**
     * Returns the guilds icon.
     *
     * @return string|null The URL to the guild icon or null.
     */
    public function getIconAttribute()
    {
        if (is_null($this->attributes['icon'])) {
            return;
        }

        return "https://discordapp.com/{$this->attributes['id']}/icons/{$this->attributes['icon']}.jpg";
    }

    /**
     * Returns the guild icon hash.
     *
     * @return string|null The guild icon hash or null.
     */
    public function getIconHashAttribute()
    {
        return $this->attributes['icon'];
    }

    /**
     * Returns the guild splash.
     *
     * @return string|null The URL to the guild splash or null.
     */
    public function getSplashAttribute()
    {
        if (is_null($this->attributes['splash'])) {
            return;
        }

        return "https://discordapp.com/api/guilds/{$this->id}/splashes/{$this->attributes['splash']}.jpg";
    }

    /**
     * Returns the guild splash hash.
     *
     * @return string|null The guild splash hash or null.
     */
    public function getSplashHashAttribute()
    {
        return $this->attributes['splash'];
    }

    /**
     * Validates the specified region.
     *
     * @return string Returns the region if it is valid or default.
     *
     * @see self::REGION_DEFAULT The default region.
     */
    public function validateRegion()
    {
        if (! in_array($this->region, $this->regions)) {
            return self::REGION_DEFUALT;
        }

        return $this->region;
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatableAttributes()
    {
        return [
            'name' => $this->name,
            'region' => $this->validateRegion(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        return [
            'name' => $this->name,
            'region' => $this->region,
            'logo' => $this->logo,
            'splash' => $this->splash,
            'verification_level' => $this->verification_level,
            'afk_channel_id' => $this->afk_channel_id,
            'afk_timeout' => $this->afk_timeout,
        ];
    }
}
