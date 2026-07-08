<?php

return [
    /*
    | How many days a user invitation link stays valid. Invitations get their
    | own (longer) lifetime than password resets — an invitee may not act for a
    | day or two — without weakening the password-reset window.
    */
    'expire_days' => (int) env('INVITATION_EXPIRE_DAYS', 3),
];
