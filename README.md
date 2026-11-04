# contenir/contenir-audience

[![Continuous Integration](https://github.com/contenir/contenir-audience/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-audience/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-audience/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-audience)

Keeps people from a [Contenir](https://github.com/contenir) site in step with a mailing list or CRM. It holds the rules
every service shares, so each service's add-on (Mailchimp, Campaign Monitor) only has to talk to its API.

- **Bindings** say which list a form or a section feeds: the field holding the email, the fields sent with it and how
  each is changed on the way, the wording a sign-up agrees to, double opt-in, tags, and what a delete does.
- **An outbox**: a sign-up or a record change only writes a message to a table in the site's database. Nothing calls a
  service while a visitor waits, so a slow or failing service never slows a page or a save.
- **A worker** sends what's due, in batches no larger than the service takes, for a limited time, behind the queue's
  lock. Busy or failing services are tried again from one minute to a day later, honouring `Retry-After`; a message
  that keeps failing is marked dead. A refused connection pauses sending and leaves everything waiting.
- **Consent**: nobody joins a list without it, and nobody who unsubscribed, bounced, complained or was erased is taken
  back without fresh consent, or at all when the reason forbids it. Each agreement is kept in a ledger: the wording,
  where, when and from which address.
- **Suppression and sync state**, kept by a peppered hash of the email, so the tables hold no addresses once a message
  is sent. An unchanged person is never sent twice.
- **A test kit** (`Contenir\Audience\Testing`): stores and a service held in memory, and a clock a test moves.

It is framework free: HTTP for the services' adapters is PSR-18, logging is PSR-3, time is a PSR-20 clock, and storage
is [php-db](https://github.com/php-db/phpdb). A service's adapter implements `ServiceAdapterInterface`; an add-on wires
the rest.

## Requirements

- PHP 8.3, 8.4 or 8.5
- php-db/phpdb 0.6 with its MySQL or SQLite driver
- peptolab/phpdb-migration 0.2, to create the tables

## Install

```bash
composer require contenir/contenir-audience
```

php-db 0.6 has no stable release yet, so the site's `composer.json` needs `"minimum-stability": "dev"` with
`"prefer-stable": true`, or `"php-db/phpdb": "0.6.x-dev@dev"` in its own `require`.

### Tables

Each add-on keeps its own tables, under its own prefix, such as `ext_mailchimp_`: an outbox, sync state, suppression,
the consent ledger, and the webhooks it registered at its service. The library ships each table's migration as an abstract class; an add-on subclasses each with
its prefix and a version of its own:

```php
final class Version20261105000100CreateOutbox extends AbstractCreateOutbox
{
    public function getVersion(): string
    {
        return '20261105000100';
    }

    protected function prefix(): string
    {
        return 'ext_mailchimp_';
    }
}
```

## Use

A binding comes from the add-on's config:

```php
$bindings = Bindings::fromConfig([
    'newsletter' => [
        'list'    => 'a1b2c3',
        'fields'  => ['FNAME' => ['from' => 'name', 'transforms' => ['trim', 'first-name']]],
        'consent' => 'Signing up to the newsletter',
    ],
]);
```

A sign-up goes in:

```php
$audience->subscribe('newsletter', ['email' => $email, 'name' => $name], new Consent(
    'Signing up to the newsletter',
    ConsentSource::Form,
    $clock->now(),
    $ip,
));
```

And the scheduler sends it:

```php
$report = $worker->drain($service);
```

## Development

```bash
composer check
```

runs formatting, lint and static analysis (Mago), the unit and integration suites (the integration suite on SQLite,
then on MySQL when `CONTENIR_TEST_DB_*` names one), and mutation testing (Infection).
