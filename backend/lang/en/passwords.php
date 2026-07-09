<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'We have emailed your password reset link.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset token is invalid.',
    'user' => "We can't find a user with that email address.",

    // Deliberately vague, single response for BOTH "link sent" and "no such
    // user" outcomes so the forgot-password endpoint can't be used to probe
    // which email addresses have accounts (anti-enumeration).
    'reset_link_generic' => 'If that email address is in our system, a reset link is on its way.',

];
