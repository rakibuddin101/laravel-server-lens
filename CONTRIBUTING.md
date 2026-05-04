# Contributing

Contributions are welcome and will be fully credited.

## Etiquette

This project is open source. Please be respectful of the time and effort that goes into it. Before opening an issue or pull request, please read this guide in full.

## How to contribute

1. Fork the repository and create your branch from `main`.
2. Make your changes. If you are adding functionality, write tests to cover it.
3. Ensure the test suite passes.
4. Make sure your code follows the existing code style (see below).
5. Submit a pull request with a clear description of what you changed and why.

## Reporting issues

Before opening an issue, please:

- Check the [existing issues](../../issues) to make sure it hasn't been reported already.
- Include as much detail as possible — Laravel version, PHP version, OS, and steps to reproduce.
- If possible, include a minimal reproduction.

## Pull Requests

- **One pull request per feature.** If you want to add multiple things, send multiple PRs.
- **Branch from `main`** — do not submit PRs against any other branch.
- **Update the README** if your change adds or alters behaviour that users need to know about.
- **Add or update tests** — PRs without tests for new behaviour may not be accepted.
- **Write a clear commit message** that explains the *why*, not just the *what*.

## Code Style

This package follows the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard. Before submitting, run:

```bash
composer lint
```

Or fix automatically with:

```bash
composer lint:fix
```

## Running Tests

```bash
composer test
```

To run tests with coverage:

```bash
composer test:coverage
```

## Security

Please do **not** open a public issue for security vulnerabilities. Review the [security policy](../../security/policy) for responsible disclosure.

## License

By contributing, you agree that your contributions will be licensed under the [MIT License](LICENSE.md).
