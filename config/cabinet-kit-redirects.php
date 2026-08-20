<?php

// Where the cabinet sends a user at each step of the auth flow. This lives in
// its own file because the landing page is the host project's decision, not the
// shell's: the package ships defaults that are guaranteed to exist, and a host
// changes them with one value instead of touching controllers.
//
// Every value is a route name. A value starting with '/' or 'http' is taken as
// a ready-made address instead. A target that resolves to no registered route
// is ignored in favour of the package default, so a stale name here can never
// take the whole sign-in flow down.
return [

    // Landing page of the cabinet root (the `route_prefix` URL itself).
    'home' => 'cabinet-kit.users',

    // Where a successful sign-in lands — password login and social sign-in alike.
    'after_login' => 'cabinet-kit.users',

    // Where registration lands. Point this at 'verification.notice' to force
    // email confirmation before the cabinet opens.
    'after_register' => 'cabinet-kit.users',

    // Where email confirmation lands.
    'after_verify' => 'cabinet-kit.users',

    // Where signing out lands. The package default leaves the cabinet entirely.
    'after_logout' => '/',

];
