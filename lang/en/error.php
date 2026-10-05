<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Error Language Lines (English)
    |--------------------------------------------------------------------------
    |
    | Messages returned by the API on error responses. Keys are grouped by
    | domain (player, team, ...). Add new keys here and in every other
    | supported locale to keep the application fully bilingual.
    |
    */

    'common' => [
        'resource_not_found' => 'Resource not found.',
    ],

    'auth' => [
        'forbidden' => 'You do not have permission to access this page.',
        'unauthorized' => 'Unauthorized.',
    ],

    'player' => [
        'player_profile_not_found' => 'Player profile not found.',
    ],

    'team' => [
        'team_not_found' => 'Team not found.',
        'player_already_applied' => 'You have already applied to this team.',
        'logo_not_found' => 'Image not found for this team.',
    ],

    'game_position' => [
        'not_found' => 'Position not found.',
    ],

    'preset' => [
        'not_found' => 'Preset not found.',
    ],

    'user' => [
        'not_found' => 'User not found.',
    ],

    'notification' => [
        'not_found' => 'Notification not found.',
        'no_recipients' => 'No recipients found for the selected audience.',
    ],

    'finance' => [
        'record_not_found' => 'Record not found.',
    ],

    'match' => [
        'not_found' => 'Match not found.',
        'cannot_edit' => 'You do not have permission to edit this match.',
        'cannot_deactivate' => 'You do not have permission to deactivate this match.',
        'cannot_reactivate' => 'You do not have permission to reactivate this match.',
        'stats_forbidden' => 'You do not have permission to manage statistics for this match.',
    ],

    'challenge' => [
        'not_found' => 'Challenge not found.',
        'forbidden' => 'Permission denied.',
        'not_open' => 'This match is not open for challenges.',
        'not_team_admin' => 'You do not manage this team.',
        'cannot_challenge_own' => 'You cannot challenge your own match.',
        'already_sent' => 'This team has already sent a challenge for this match.',
        'not_pending' => 'This challenge is no longer pending.',
        'cannot_decline_confirmed' => 'A confirmed challenge cannot be declined.',
        'cannot_cancel_confirmed' => 'A confirmed challenge cannot be cancelled.',
        'host_not_accepted' => 'The host has not accepted this challenge yet.',
    ],

    'email_verification' => [
        'link_expired' => 'The verification link has expired. Please request a new one.',
        'invalid_link' => 'Invalid verification link.',
        'throttled' => 'Please wait 60 seconds before requesting another email.',
    ],

    'password_reset' => [
        'invalid_token' => 'Invalid or expired token.',
    ],

];
