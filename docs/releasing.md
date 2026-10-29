# Releasing the core and CodeIgniter adapter

The source of truth for both packages is `daycry/doctrine`. The adapter distribution
is [daycry/doctrine-codeigniter4](https://github.com/daycry/doctrine-codeigniter4).
Do not edit the distribution repository directly: a subsequent split would omit
those changes.

## Before tagging

1. Update the changelog, migration guide and installation examples.
2. Open a pull request against `master`. Code style, PHPStan, Psalm, Rector,
   PHPUnit, standalone installation and documentation builds must pass.
3. Check that PHPUnit is active: GitHub can disable scheduled workflows after
   repository inactivity. Use `gh workflow enable phpunit.yml` if needed.
4. Merge the reviewed commit and wait for the corresponding master checks.
5. Validate both Composer manifests and test the package archives. The root
   `.gitattributes` excludes the adapter and development files from Git archives;
   Composer's `archive.exclude` also controls locally generated Composer archives.

Run all commands with PHP 8.5. The normal development checks are:

```bash
composer validate --strict
composer validate --strict --working-dir=packages/codeigniter4
composer test -- --no-coverage
php vendor/bin/phpunit -c phpunit.standalone.xml.dist --no-coverage
composer analyze
composer cs
python -m mkdocs build --strict --site-dir build/docs
```

## Publish matching versions

From the tested master commit, export only the adapter's history:

```bash
git fetch origin
git subtree split --prefix=packages/codeigniter4 origin/master -b release/adapter-6.0.0
git push https://github.com/daycry/doctrine-codeigniter4.git release/adapter-6.0.0:main
```

Resolve and record both full commit IDs before creating tags. The following
commands show the first release; replace version numbers for later releases:

```bash
git tag -a v6.0.0 origin/master -m "Release 6.0.0"
git push origin refs/tags/v6.0.0
gh release create v6.0.0 --repo daycry/doctrine --verify-tag --title "6.0.0" --notes-file build/release-notes.md
gh api --method POST repos/daycry/doctrine-codeigniter4/git/refs -f ref=refs/tags/v6.0.0 -f sha=ADAPTER_COMMIT_SHA
gh release create v6.0.0 --repo daycry/doctrine-codeigniter4 --verify-tag --title "6.0.0" --notes-file build/adapter-release-notes.md
```

Replace `ADAPTER_COMMIT_SHA` with the split commit ID. The notes files must exist
and describe the final release. Never move an existing published version tag.
Stop publication if a required check fails; publish a patch version for corrections
after a release rather than replacing its contents.

## Packagist and installation verification

The existing core package must index `v6.0.0`. Register the adapter's public GitHub
repository on [Packagist](https://packagist.org/packages/submit) using an account
authorized for the package, then enable its GitHub update integration.
Creating a GitHub repository or release does not register a Packagist package.

Until the adapter is indexed, consumers can install its tagged VCS distribution:

```bash
composer config repositories.doctrine-codeigniter4 vcs https://github.com/daycry/doctrine-codeigniter4
composer require daycry/doctrine-codeigniter4:^6.0
```

Verify a fresh consumer using the published tags, rather than local path
repositories. Confirm that a core-only install has no CodeIgniter dependency and
that the adapter's services, database connection and Spark commands are discovered.
The [installation guide](installation.md) describes the application setup.

## Documentation and recovery

The Docs workflow builds pull requests without deployment and publishes from
`master` to the `github-pages` environment. Its deployment policy must permit
`master`. Verify the deployed site after merging.

An application can restore its previous lockfile and deployed code when reverting
an upgrade; the library release itself does not execute schema migrations. Any
application-owned data/schema changes need their own recovery procedure.
