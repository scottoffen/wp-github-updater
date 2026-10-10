# Security Policy

This policy covers the `scottoffen/wp-github-updater` package. The package sends requests to GitHub with an access token on behalf of a WordPress site, and it installs the zip archive that it downloads.

## Supported Versions

Only the latest release of the 1.x series receives security fixes, so reproduce a problem on that release before you report it.

## Reporting a Vulnerability

Report a vulnerability privately. A public issue, discussion, or pull request exposes the problem to everyone before a fix exists.

Use GitHub's private vulnerability reporting. Open the [advisory form](https://github.com/scottoffen/wp-github-updater/security/advisories/new) and describe the problem. The GitHub documentation explains how to [privately report a security vulnerability](https://docs.github.com/en/code-security/how-tos/report-and-fix-vulnerabilities/report-a-vulnerability/privately-reporting-a-security-vulnerability).

Include the following in the report.

- The version of the package, and the versions of WordPress and PHP.
- The steps that reproduce the problem, with a proof of concept if you have one.
- The effect of the problem, such as the exposure of an access token or the installation of a file that the release didn't contain.

Never include a working access token in a report. Revoke any token that you used while testing.

## Response

The maintainer acknowledges a report within seven days. A confirmed problem is fixed in a new release and published as a GitHub security advisory that credits the reporter, unless the reporter asks to remain anonymous. Keep the details private until the advisory is published.

## Scope

The following areas are in scope.

- The handling of the access token, including the addresses that it is sent to and the place where it is saved.
- The checks on the addresses that the updater requests and follows, and the checks on the downloaded file.
- The capability and nonce checks on the updater-provided settings section.
- The escaping of values in the settings section, in the release notes page of a theme, and in the details popup of a plugin.

The following areas are out of scope.

- Vulnerabilities in WordPress, PHP, or GitHub. Report them to their own maintainers.
- Vulnerabilities in `scottoffen/markdown-converter`. Report them in the repository of that package.
- A token that a site owner exposes through the configuration of the site.
- A harmful zip that the owner of the configured repository publishes. The updater installs the release asset of the repository that the site owner configures.
