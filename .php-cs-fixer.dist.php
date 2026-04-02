<?php
/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

return (new PhpCsFixer\Config())
    ->setRules([
        '@PHP71Migration' => true,
        '@Symfony' => true,
        'protected_to_private' => false,
        'nullable_type_declaration_for_default_null_value' => ['use_nullable_type_declaration' => false],
        'phpdoc_to_comment' => false,
    ])
    ->setRiskyAllowed(false)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__)
            ->exclude([
                'data',
                'library',
                'locale',
                'themes',
                'bb-vendor',
                'install',
                'modules/Wysiwyg'
            ])
            ->notPath('rb.php')
    )
;
