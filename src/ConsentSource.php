<?php

declare(strict_types=1);

namespace Contenir\Audience;

/**
 * Where a person's agreement to be on a list came from.
 *
 * @api
 */
enum ConsentSource: string
{
    /** A sign-up form on the site */
    case Form = 'form';

    /** A record in the CMS that holds its own consent field */
    case Record = 'record';

    /** A list brought over from somewhere else, such as a site's earlier integration */
    case Import = 'import';
}
