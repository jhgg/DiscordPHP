<?php

/*
 * This file is apart of the DiscordPHP project.
 *
 * Copyright (c) 2016 David Cole <david@team-reflex.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the LICENSE.md file.
 */

namespace Discord\Parts\User;

use Carbon\Carbon;
use Discord\Helpers\Collection;
use Discord\Helpers\Guzzle;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Guild\Role;
use Discord\Parts\Part;
use Discord\Parts\Permissions\RolePermission as Permission;

/**
 * A member is a relationship between a user and a guild. It contains user-to-guild specific data like roles.
 */
class Member extends Part
{
    /**
     * {@inheritdoc}
     */
    protected $fillable = ['user', 'roles', 'deaf', 'mute', 'joined_at', 'guild_id', 'status', 'game'];

    /**
     * {@inheritdoc}
     */
    public $creatable = false;

    /**
     * {@inheritdoc}
     */
    public $findable = false;

    /**
     * {@inheritdoc}
     */
    protected $fillAfterSave = false;

    /**
     * {@inheritdoc}
     */
    protected $uris = [
        'get' => '',
        'create' => '',
        'update' => 'guilds/:guild_id/members/:id',
        'delete' => 'guilds/:guild_id/members/:id',
    ];

    /**
     * Alias for delete.
     *
     * @return bool Whether the attempt to kick the member succeeded or failed.
     *
     * @see \Discord\Parts\Part::delete() This function is an alias for delete.
     */
    public function kick()
    {
        return $this->delete();
    }

    /**
     * Moves the member to another voice channel.
     *
     * @param Channel|int $channel The channel to move the member to.
     *
     * @return bool Whether the moving succeeded or failed.
     */
    public function moveMember($channel)
    {
        if ($channel instanceof Channel) {
            $channel = $channel->id;
        }

        Guzzle::patch("guilds/{$this->guild_id}/members/{$this->id}", [
            'channel_id' => $channel,
        ]);

        // At the moment we are unable to check if the member
        // was moved successfully.

        return true;
    }

    /**
     * Adds a role to the member.
     *
     * @param Role|int $role The role to add to the member.
     *
     * @return bool Whether adding the role succeeded or failed.
     */
    public function addRole($role)
    {
        if (is_int($role)) {
            $role = new Role(['id' => $role], true);
        }

        // We don't want a double up on roles
        if (false !== array_search($role->id, (array) $this->attributes['roles'])) {
            return false;
        }

        // Preload the roles if there is no collection
        $this->getRolesAttribute();

        $this->attributes['roles'][] = $role->id;
        $this->attributes_cache['roles']->push($role);

        return true;
    }

    /**
     * Removes a role from the user.
     *
     * @param Role|int $role The role to remove from the member.
     *
     * @return bool Whether removing the role succeeded or failed.
     */
    public function removeRole($role)
    {
        if ($role instanceof Role) {
            $role = $role->id;
        }

        if (false !== $index = array_search($role, $this->attributes['roles'])) {
            unset($this->attributes['roles'][$index]);
        }

        $rolePart = $this->roles->get('id', $role);

        if (false !== $index = array_search($rolePart, $this->roles->all())) {
            $this->roles->pull($index);
        }

        return true;
    }

    /**
     * Returns the id attribute.
     *
     * @return int The user ID of the member.
     */
    public function getIdAttribute()
    {
        return $this->user->id;
    }

    /**
     * Returns the username attribute.
     *
     * @return string The username of the member.
     */
    public function getUsernameAttribute()
    {
        return $this->user->username;
    }

    /**
     * Returns the user attribute.
     *
     * @return User The user that owns the member.
     */
    public function getUserAttribute()
    {
        return new User((array) $this->attributes['user'], true);
    }

    /**
     * Returns the roles attribute.
     *
     * @return Collection A collection of roles the member is in.
     */
    public function getRolesAttribute()
    {
        if (isset($this->attributes_cache['roles'])) {
            return $this->attributes_cache['roles'];
        }

        $roles = [];
        $request = Guzzle::get($this->replaceWithVariables('guilds/:guild_id/roles'));

        foreach ($request as $key => $role) {
            if (! (false === array_search($role->id, (array) $this->attributes['roles']))) {
                $perm = new Permission([
                    'perms' => $role->permissions,
                ]);
                $role = (array) $role;
                $role['permissions'] = $perm;
                $roles[] = new Role($role, true);
            }
        }

        $roles = new Collection($roles);

        $this->attributes_cache['roles'] = $roles;

        return $roles;
    }

    /**
     * Returns the joined at attribute.
     *
     * @return Carbon The timestamp from when the member joined.
     */
    public function getJoinedAtAttribute()
    {
        return new Carbon($this->attributes['joined_at']);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        return [
            'roles' => $this->attributes['roles'],
        ];
    }

    /**
     * Returns a formatted mention.
     *
     * @return string
     */
    public function __toString()
    {
        return "<@{$this->user->id}>";
    }
}
