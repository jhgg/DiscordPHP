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

use Discord\Exceptions\FileNotFoundException;
use Discord\Exceptions\PasswordEmptyException;
use Discord\Helpers\Collection;
use Discord\Helpers\Guzzle;
use Discord\Parts\Guild\Guild;
use Discord\Parts\Part;

/**
 * The client is the main interface for the client. Most calls on the main class are forwarded here.
 */
class Client extends Part
{
    /**
     * {@inheritdoc}
     */
    public $creatable = false;

    /**
     * {@inheritdoc}
     */
    public $deletable = false;

    /**
     * {@inheritdoc}
     */
    public $findable = false;

    /**
     * {@inheritdoc}
     */
    protected $fillable = ['id', 'username', 'password', 'email', 'verified', 'avatar', 'discriminator'];

    /**
     * {@inheritdoc}
     */
    protected $uris = [
        'update' => 'users/@me',
    ];

    /**
     * Runs any extra construction tasks.
     *
     * @return void
     */
    public function afterConstruct()
    {
        $this->user = new User([
            'id' => $this->id,
            'username' => $this->username,
            'avatar' => $this->attributes['avatar'],
            'discriminator' => $this->discriminator,
        ], true);
    }

    /**
     * Sets the users avatar.
     *
     * @param string $filepath The path to the file.
     *
     * @return bool Whether the setting succeeded or failed.
     *
     * @throws \Discord\Exceptions\FileNotFoundException Thrown when the file does not exist.
     */
    public function setAvatar($filepath)
    {
        if (! file_exists($filepath)) {
            throw new FileNotFoundException("File does not exist at path {$filepath}.");
        }

        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $file = file_get_contents($filepath);
        $base64 = base64_encode($file);

        $this->attributes['avatarhash'] = "data:image/{$extension};base64,{$base64}";

        return true;
    }

    /**
     * Updates the clients presence.
     *
     * @param WebSocket   $ws       The WebSocket client.
     * @param string|null $gamename The game that you are playing or null.
     * @param bool        $idle     Whether you are set to idle.
     *
     * @return bool Whether the setting succeeded or failed.
     */
    public function updatePresence($ws, $gamename, $idle)
    {
        $idle = ($idle == false) ? null : true;

        $ws->send([
            'op' => 3,
            'd' => [
                'game' => (! is_null($gamename) ? [
                    'name' => $gamename,
                ] : null),
                'idle_since' => $idle,
            ],
        ]);

        return true;
    }

    /**
     * Returns an array of Guilds.
     *
     * @return Collection A collection of guilds.
     */
    public function getGuildsAttribute()
    {
        if (isset($this->attributes_cache['guilds'])) {
            return $this->attributes_cache['guilds'];
        }

        $guilds = [];
        $request = Guzzle::get('users/@me/guilds');

        foreach ($request as $index => $guild) {
            $guilds[$index] = new Guild((array) $guild, true);
        }

        $guilds = new Collection($guilds);

        $this->attributes_cache['guilds'] = $guilds;

        return $guilds;
    }

    /**
     * Returns the avatar URL for the client.
     *
     * @return string The URL to the client's avatar.
     */
    public function getAvatarAttribute()
    {
        if (empty($this->attributes['avatar'])) {
            return;
        }

        return "https://discordapp.com/api/users/{$this->id}/avatars/{$this->attributes['avatar']}.jpg";
    }

    /**
     * Returns the avatar ID for the client.
     *
     * @return string The avatar ID for the client.
     */
    public function getAvatarIDAttribute()
    {
        return $this->avatar;
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatableAttributes()
    {
        if (empty($this->attributes['password'])) {
            throw new PasswordEmptyException('You must enter your password to update your profile.');
        }

        $attributes = [
            'username' => $this->attributes['username'],
            'email' => $this->email,
            'password' => $this->attributes['password'],
            'avatar' => $this->attributes['avatarhash'],
        ];

        if (! empty($this->attributes['new_password'])) {
            $attributes['new_password'] = $this->attributes['new_password'];
        }

        return $attributes;
    }
}
