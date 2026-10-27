# Contributing

We accept contributions as pull requests. Read these guidelines before you open a pull request.

## Process

1. Fork the project.
2. Make a new branch.
3. Write the code and the tests. Then commit and push.
4. Open a pull request. Tell what you changed and why.

## Guidelines

- Format the code with `composer format`.
- Make each commit in your pull request a complete change.
- [Rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) your branch when it has merge conflicts.
- We use [SemVer](https://semver.org/).
- Write documentation and comments in [ASD-STE100 Simplified Technical English](https://www.asd-ste100.org/).

## Setup

Clone your fork, then install the dependencies:

```bash
composer install
```

## Tests

Run the tests:

```bash
composer test
```

The tests use OpenSpout 5. To run them with OpenSpout 4:

```bash
composer update openspout/openspout --with openspout/openspout:^4.30 -W
```

Run the static analysis:

```bash
composer phpstan
```
