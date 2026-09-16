<?php

// English copy of trigger emails — see lang/uk/emails.php for the structure and the rules
// about ':placeholder' link anchors.
return [

    'telegram' => [
        'name' => 'Got a question or a problem?',
        'desc' => 'We are on Telegram — write to us if anything is unclear or does not work, and we will sort it out.',
        'cta'  => 'Message us on Telegram',
    ],

    'layout' => [
        'tagline'        => 'cloud retail sales management system',
        'team'           => 'the Posio team',
        'footer_note'    => 'You received this email from Posio.',
        'unsubscribe'      => 'Stop receiving emails',
        'unsubscribe_link' => 'Here',
        'rights'         => 'All rights reserved.',
        'fallback_title' => 'Button not working?',
        'fallback_text'  => 'Copy the link into your browser:',
    ],

    'welcome_own' => [
        'subject'         => 'Welcome to Posio — how to get started',
        'title'           => 'Welcome to Posio!',
        'preheader'       => 'Three steps to connect your register and start selling.',
        'eyebrow'         => 'Up and running in 5 minutes',
        'heading'         => ':name, your Posio account is ready',
        'heading_noname'  => 'Your Posio account is ready',
        'lead'            => 'Thank you for choosing Posio. You are the account owner, so every setting is in your hands. These steps will get your register working in no time:',
        'footer_note'     => 'You received this email because you registered an account with Posio.',
        'signoff'         => 'Have a great start',

        'step1_title'     => 'Add your products',
        'step1_text'      => 'Enter them manually or import from Excel in the :products section.',
        'step1_link'      => 'Products',

        'step2_title'     => 'Connect the register',
        'step2_text'      => 'Your first register is already created. In the :cashboxes section copy the activation link and open it on the register device (:front works too — enter the licence key there).',
        'step2_link'      => 'Cashboxes',

        'step3_title'     => 'Create your first receipt',
        'step3_text'      => 'Sign in as the test cashier (PIN 1111), pick a few products and take a payment. The receipt lands in the register journal of your cabinet automatically.',

        'qr_title'        => 'Tablet or phone — scan a QR code',
        'qr_text'         => 'Press the QR button on the register card in your cabinet and scan the code with the device camera. The register connects itself — nothing to type in.',

        'cta'             => 'Add products',
        'tip'             => 'Tip: to open this in a full browser, right-click the button on a computer and choose “Open link in new tab”; on a phone press and hold it for a few seconds and choose “Open in Chrome”.',
    ],

    'welcome_connected' => [
        'subject'                 => 'Welcome to the team on Posio',
        'subject_company'         => 'Welcome to the :company team on Posio',
        'title'                   => 'Welcome to the team on Posio',
        'preheader'               => 'The shop is already trading — here is where an administrator starts.',
        'eyebrow'                 => 'You are on the team',
        'heading'                 => ':name, welcome to the :company team',
        'heading_noname'          => 'Welcome to the :company team',
        'heading_nocompany'       => ':name, you have been added to a Posio account',
        'heading_nocompany_noname'=> 'You have been added to a Posio account',
        'lead'                    => 'The account owner gave you the “Administrator” role — the shop is already trading, and you are joining in to run it from the cabinet. Here is where to start:',
        'footer_note'             => 'You received this email because you were added to a Posio account.',
        'signoff'                 => 'Enjoy your work',

        'step1_title'             => 'See how trading is going',
        'step1_text'              => 'The :dashboard shows the summary of trading, and the :report has the detail by product, register and cashier.',
        'step1_link_dashboard'    => 'dashboard',
        'step1_link_report'       => 'sales report',

        'step2_title'             => 'Check products and prices',
        'step2_text'              => 'The catalogue the register works from is the :products section under “Dictionaries”: items, prices and stock.',
        'step2_link'              => 'Products',

        'step3_title'             => 'Goods received and supplier settlements',
        'step3_text'              => 'The :purchases section records goods received from a supplier — and settlements with that supplier are kept there too.',
        'step3_link'              => 'Purchase of Goods',

        'step4_title'             => 'Money beyond goods, and reporting',
        'step4_text'              => 'The :financial section covers money that is not tied to goods: other income and expenses, transfers between accounts and registers. Totals for the accountant are in the :report.',
        'step4_link_financial'    => 'Current Accounts',
        'step4_link_report'       => 'financial report',

        'cta'                     => 'Open the cabinet',
    ],

    'activate_account' => [
        'subject'                => 'Create your company in Posio — the first register is free',
        'title'                  => 'Create your company in Posio',
        'preheader'              => 'Register, stock and reports in one cabinet. All that is left is to create your company.',
        'eyebrow'                => 'Your account is almost ready',
        'heading'                => ':name, start selling with Posio',
        'heading_noname'         => 'Start selling with Posio',
        'lead'                   => 'We noticed that you haven’t completed your registration with our service — a cloud-based POS system for shops, cafés, and service businesses. Here’s what you get:',

        'benefit1_title'         => 'A mobile register — the first one free',
        'benefit1_text'          => 'Runs on your own phone, tablet or laptop, and keeps working offline when the network drops. Integration with fiscal registrars (PRRO) and receipt fiscalisation. A clear, capable interface for the cashier.',
        'benefit2_title'         => 'Stock and cost of goods',
        'benefit2_text'          => 'See stock levels, purchase prices and what you actually earn on every sale.',
        'benefit3_title'         => 'Reporting for the owner',
        'benefit3_text'          => 'Financial reporting right on your phone — cash flow and P&L.',
        'benefit4_title'         => 'Your team works together',
        'benefit4_text'          => 'Cashiers at the counter, you in the reports. Add venue administrators for day-to-day tasks: everyone sees only what they need.',
        'benefit5_title'         => 'Support that helps',
        'benefit5_text'          => 'An AI assistant explains how the service works, and when that is not enough we will advise you and set everything up together with you.',

        'bottom'                 => 'All that is left is to create your company — and you can ring up your first receipt.',
        'cta'                    => 'Create company',
        'callout'                => 'Not keen on working it out alone? Message us on Telegram — we will walk you from creating the company to your first receipt.',
        'footer_note_verify'     => 'You received this email because your registration with Posio is not finished yet. Did not sign up? Just ignore this email.',
        'footer_note_no_account' => 'You received this email because you have not created a company in Posio yet.',
        'signoff'                => 'See you in Posio',
    ],

];
