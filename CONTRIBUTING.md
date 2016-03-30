# Contributing

Contributions are welcome! You can help making this module better by explaining
a bug, suggest a change or a new feature. The correct way to do
that is to [create an issue](https://github.com/silverstripe-labs/silverstripe-crontask/issues).

We accept proposed code changes via [pull requests](https://github.com/silverstripe-labs/silverstripe-crontask/pulls).

## Tests

Tests must pass and new functionality should be extended to cover any new lines
of code. See the [SilverStripe Unit and Integration Testing](https://docs.silverstripe.org/en/3.3/developer_guides/testing/)
documentation.

## Code style

The code to this module is following the [PSR-2 standard](http://www.php-fig.org/psr/psr-2/)
with the exception of SilverStripe spefic variable naming like `private static
$has_many` etc. We will not merge code that doesn't adhere to the standard.
Every push to a PR will run a code quality check via https://scrutinizer-ci.com/ and the results will
show in the Github PR.

It's also possible to use the [PHP Coding Standards Fixer](https://github.com/FriendsOfPhp/PHP-CS-Fixer)
to check and fix the code style for you.

## Commit messages

Having good commit messages serves multiple purposes:

- To speed up the reviewing process.
- To help us write a good release note.
- To help the future maintainers of this module finding out why a particular
  change was made to the code or why a specific feature was added.

Structure your commit message like this:

> Short (50 chars or less) summary of changes
>
> More detailed explanatory text, if necessary. Wrap it to about 72 characters
> or so. In some contexts, the first line is treated as the subject of an email
> and the rest of the text as the body. The blank line separating the summary
> from the body is critical (unless you omit the body entirely); tools like
> rebase can get confused if you run the two together.
>
> Further paragraphs come after blank lines.
>
>  * Bullet points are okay, too
>
>  * An asterisk is used for the bullet, preceded by a single space, with blank
>    lines in between, but conventions vary here
>
> Source [http://git-scm.com/book/ch5-2.html](http://git-scm.com/book/ch5-2.html)

Write the summary line and description of what you have done in the imperative
mode, that is as if you were commanding someone. Start the line with "`Fix`",
"`Add`", "`Change`" instead of "Fixed", "Added", "Changed".

Don't end the summary line with a period - it's a title and titles don't end
with a period.

Always leave the second line blank.

If you are having trouble expressing the change in a short subject line, you
probably need to make several commits, see below:

## How to group changes together

Group related commits into one commit. Try finding a set of changes that
describes a minmal working chnage and commit them as one change. This might be
better descibed with a good and bad example:

*Bad*

 - change variable name to blah
 - add check if user is logged in
 - clean up whitespace
 - reset users password and send email

*Good*

 - add possibility for users to reset passwords

*Bad*

 - fix bug on user password reset and remove old password resets

*Good*

 - fix bug on user password reset
 - remove old reset tokens from database

