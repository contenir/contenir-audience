# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Bindings read from config, with field maps and their transforms.
- The consent policy, back-off, outbox worker and the handling of what a service reports through its webhook.
- php-db stores for the outbox, sync state, suppression and consent ledger, with abstract migrations an add-on
  subclasses under its own prefix.
- A test kit: stores and a service held in memory, and a clock a test moves.
