<?php

return [
    'title' => 'Security',
    'icon' => 'fa-shield-halved',
    'intro' => 'Protect your account with two-factor authentication (2FA) and see which devices can sign in. With 2FA on, nobody can log in with your password alone.',
    'features' => [
        ['icon' => 'fa-mobile-screen', 'title' => 'Authenticator app', 'text' => 'Click Set up to link Authy, Google Authenticator or Microsoft Authenticator. After your password, you then enter the 6-digit code from the app each time you sign in.'],
        ['icon' => 'fa-envelope', 'title' => 'New device email check', 'text' => 'When 2FA is on, signing in from a new computer or browser also asks for a code sent to your email. After that, the device is remembered.'],
        ['icon' => 'fa-key', 'title' => 'Recovery codes', 'text' => 'You get 8 one-time recovery codes when you turn on 2FA. Use one to sign in if you lose your phone. Click Generate new codes to replace them.'],
        ['icon' => 'fa-rotate', 'title' => 'Reconfigure', 'text' => 'Moving to a new phone? Click Reconfigure and scan the new QR code. Your old phone keeps working until the new one is verified.'],
        ['icon' => 'fa-laptop', 'title' => 'Recognised devices', 'text' => 'See the browsers that passed email verification and when they were last used. Click Forget other devices to make every other device verify by email again.'],
        ['icon' => 'fa-building', 'title' => '2-Factor Authentication (My Company)', 'text' => 'Agencies only. Turn this switch on to make 2FA mandatory for your agency account and every agent in it. Agents without it must set it up before they can use the portal.'],
    ],
    'steps' => [
        ['title' => 'Install an authenticator app', 'text' => 'Install Authy, Google Authenticator or Microsoft Authenticator on your phone.'],
        ['title' => 'Click Set up', 'text' => 'In the app, tap Add account and scan the QR code. If you cannot scan it, type in the key shown under the code.'],
        ['title' => 'Enter the 6-digit code', 'text' => 'Type the code your app shows for MW Realty and click Verify & Enable.'],
        ['title' => 'Save your recovery codes', 'text' => 'Copy or Download the recovery codes and keep them somewhere safe. They are shown only once.'],
    ],
    'tips' => [
        'Reconfigure, Generate new codes and Disable 2FA all ask for your password first.',
        'If your agency has made 2FA mandatory, you cannot turn it off.',
        'Lost your phone and your recovery codes? Raise a ticket in Contact Us and ask MW Realty support to reset 2FA.',
        'If a code is not accepted, check that your phone\'s time is set correctly and that you scanned the latest QR code.',
    ],
];
