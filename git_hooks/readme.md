# Disclaimer

Only use this in unixy environment at your own risk.


# Githooks-Developer Tools

Introducing Githooks in order to streamline the
development and catch possible errors at an early stage
during the release.


# Introduction

Githooks are scripts that are called at certain events
in a git-repository, e.g. pre-commit or post-commit, which
are called (nomen est omen) right  before or after a commit
process is initiatet (for a complete list of possible githooks
please refer to git documentation).

They are placed in the `.git/hooks/`, but are called in the
repository root. The script `install.sh`, that should be called from
the repo root (`./git_hooks/install.sh`), will do this for you atomatically.
You may add your hooks simply by adding them into the git_hooks/hooks folder.
We take this approach, since there is no simple way to push local
hooks to a remote repo.
Utility-scripts, which are called by the git hooks, should be placed
in the `support`-folder. Also, please refer to the already present
pre-commit hook, which should serve as a fine example.

