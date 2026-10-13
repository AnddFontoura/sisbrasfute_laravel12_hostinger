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

    'court' => [
        'not_found' => 'Court not found.',
        'access_denied' => 'You do not have access to the courts module.',
        'not_owner' => 'You do not have permission to access this court.',
        'upload_failed' => 'Failed to upload court photos.',
        'invalid_status_transition' => 'This court cannot transition to the requested status.',
        'photos_range' => 'A court must have between 1 and 10 photos.',
        'duplicate_schedule_weekday' => 'Each weekday can only be configured once.',
        'schedule_window_required' => 'Active days require a start time, end time and reservation duration.',
        'schedule_window_invalid' => 'The time window must be valid and fit at least one reservation slot.',
    ],

    'court_invitation' => [
        'forbidden' => 'You do not have permission to manage this court team.',
        'not_found' => 'Invitation not found.',
        'expired' => 'This invitation has expired.',
        'already_resolved' => 'This invitation has already been answered.',
        'email_mismatch' => 'This invitation was sent to a different email.',
        'duplicate_pending' => 'There is already a pending invitation for this email on this court.',
        'already_member' => 'This person is already part of this court team.',
        'cannot_invite_self' => 'You cannot invite yourself.',
        'cannot_remove_owner' => 'The owner cannot be removed from the team.',
        'cannot_set_owner' => 'The owner role cannot be assigned.',
    ],

];
