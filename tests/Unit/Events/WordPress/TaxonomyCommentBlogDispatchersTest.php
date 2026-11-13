<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Events\WordPress\Blog\BlogArchived;
use Pollora\Events\WordPress\Blog\BlogCreated;
use Pollora\Events\WordPress\Blog\BlogDeleted;
use Pollora\Events\WordPress\Blog\BlogEventDispatcher;
use Pollora\Events\WordPress\Blog\BlogMarkedAsMature;
use Pollora\Events\WordPress\Blog\BlogMarkedAsNotMature;
use Pollora\Events\WordPress\Blog\BlogMarkedAsNotSpam;
use Pollora\Events\WordPress\Blog\BlogMarkedAsSpam;
use Pollora\Events\WordPress\Blog\BlogRestored;
use Pollora\Events\WordPress\Blog\BlogTrashed;
use Pollora\Events\WordPress\Blog\BlogUnarchived;
use Pollora\Events\WordPress\Blog\BlogVisibilityUpdated;
use Pollora\Events\WordPress\Comment\CommentCreated;
use Pollora\Events\WordPress\Comment\CommentDeleted;
use Pollora\Events\WordPress\Comment\CommentEventDispatcher;
use Pollora\Events\WordPress\Comment\CommentRestored;
use Pollora\Events\WordPress\Comment\CommentSpammed;
use Pollora\Events\WordPress\Comment\CommentStatusChanged;
use Pollora\Events\WordPress\Comment\CommentTrashed;
use Pollora\Events\WordPress\Comment\CommentUpdated;
use Pollora\Events\WordPress\Taxonomy\TaxonomyEventDispatcher;
use Pollora\Events\WordPress\Taxonomy\TermCreated;
use Pollora\Events\WordPress\Taxonomy\TermDeleted;
use Pollora\Events\WordPress\Taxonomy\TermUpdated;
use Tests\Unit\Events\Fixtures\FakeComment;
use Tests\Unit\Events\Fixtures\FakeSite;
use Tests\Unit\Events\Fixtures\FakeTerm;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

describe('TaxonomyEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(TaxonomyEventDispatcher::class);
        $this->term = new FakeTerm('term');

        Functions\when('get_term')->alias(fn (int $id, string $taxonomy): ?object => $id === 5 && $taxonomy === 'category' ? $this->term : null);
        Functions\when('is_wp_error')->alias(fn (mixed $value): bool => $value instanceof WP_Error);
    });

    it('dispatches TermCreated with the term of the right taxonomy', function (): void {
        $this->dispatcher->handleCreatedTerm(5, 9, 'category');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TermCreated::class)
            ->and($this->log[0]->term)->toBe($this->term);
    });

    it('ignores the creation of a navigation menu term', function (): void {
        $this->dispatcher->handleCreatedTerm(5, 9, 'nav_menu');

        expect($this->log)->toHaveCount(0);
    });

    it('ignores a term WordPress cannot load', function (mixed $loaded): void {
        Functions\when('get_term')->justReturn($loaded);

        $this->dispatcher->handleCreatedTerm(5, 9, 'category');
        $this->dispatcher->handleEditedTerm(5, 9, 'category');

        expect($this->log)->toHaveCount(0);
    })->with([
        'nothing' => [null],
        'an error' => [new WP_Error],
    ]);

    it('dispatches TermDeleted with the term WordPress passes along', function (): void {
        $this->dispatcher->handleDeleteTerm(5, 9, 'category', $this->term);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TermDeleted::class)
            ->and($this->log[0]->term)->toBe($this->term);
    });

    it('ignores the deletion of a navigation menu term', function (): void {
        $this->dispatcher->handleDeleteTerm(5, 9, 'nav_menu', $this->term);

        expect($this->log)->toHaveCount(0);
    });

    it('reports the term as it was before the edit alongside the edited one', function (): void {
        $before = new FakeTerm('before');
        $after = new FakeTerm('after');

        Functions\when('get_term')->justReturn($before);
        $this->dispatcher->handleEditTerm(5, 9, 'category');

        Functions\when('get_term')->justReturn($after);
        $this->dispatcher->handleEditedTerm(5, 9, 'category');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(TermUpdated::class)
            ->and($this->log[0]->term)->toBe($after)
            ->and($this->log[0]->previousTerm)->toBe($before);
    });

    it('forgets the previous term once the update has been reported', function (): void {
        Functions\when('get_term')->justReturn(new FakeTerm('before'));
        $this->dispatcher->handleEditTerm(5, 9, 'category');
        $this->dispatcher->handleEditedTerm(5, 9, 'category');

        $this->dispatcher->handleEditedTerm(5, 9, 'category');

        expect($this->log)->toHaveCount(2)
            ->and($this->log[1]->previousTerm)->toBeNull();
    });

    it('ignores the edit of a navigation menu term', function (): void {
        $this->dispatcher->handleEditedTerm(5, 9, 'nav_menu');

        expect($this->log)->toHaveCount(0);
    });
});

describe('CommentEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(CommentEventDispatcher::class);
        $this->comment = new FakeComment('comment');

        Functions\when('get_comment')->alias(fn (int $id): ?object => $id === 3 ? $this->comment : null);
    });

    it('dispatches CommentCreated with the inserted comment', function (): void {
        $this->dispatcher->handleWpInsertComment(3, $this->comment);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(CommentCreated::class)
            ->and($this->log[0]->comment)->toBe($this->comment);
    });

    it('maps each comment hook to its event with the loaded comment', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}(3);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->comment)->toBe($this->comment);
    })->with([
        'edit_comment' => ['handleEditComment', CommentUpdated::class],
        'trash_comment' => ['handleTrashComment', CommentTrashed::class],
        'untrash_comment' => ['handleUntrashComment', CommentRestored::class],
        'spam_comment' => ['handleSpamComment', CommentSpammed::class],
        'delete_comment' => ['handleDeleteComment', CommentDeleted::class],
    ]);

    it('reports unspamming as a status change from spam to approved', function (): void {
        $this->dispatcher->handleUnspamComment(3);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(CommentStatusChanged::class)
            ->and($this->log[0]->comment)->toBe($this->comment)
            ->and($this->log[0]->oldStatus)->toBe('spam')
            ->and($this->log[0]->newStatus)->toBe('approved');
    });

    it('hands the old and new status to the status change event in that order', function (): void {
        $this->dispatcher->handleTransitionCommentStatus('approved', 'unapproved', $this->comment);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(CommentStatusChanged::class)
            ->and($this->log[0]->comment)->toBe($this->comment)
            ->and($this->log[0]->oldStatus)->toBe('unapproved')
            ->and($this->log[0]->newStatus)->toBe('approved');
    });

    it('ignores pingbacks and trackbacks everywhere', function (string $type): void {
        $ping = new FakeComment($type);
        Functions\when('get_comment')->justReturn($ping);

        $this->dispatcher->handleWpInsertComment(3, $ping);
        $this->dispatcher->handleTransitionCommentStatus('approved', 'hold', $ping);
        foreach (['handleEditComment', 'handleTrashComment', 'handleUntrashComment', 'handleSpamComment', 'handleUnspamComment', 'handleDeleteComment'] as $handler) {
            $this->dispatcher->{$handler}(3);
        }

        expect($this->log)->toHaveCount(0);
    })->with(['pingback', 'trackback']);

    it('keeps other comment types such as a review', function (): void {
        $reply = new FakeComment('review');

        $this->dispatcher->handleWpInsertComment(3, $reply);

        expect($this->log)->toHaveCount(1);
    });

    it('ignores a comment that no longer exists', function (): void {
        foreach (['handleEditComment', 'handleTrashComment', 'handleUntrashComment', 'handleSpamComment', 'handleUnspamComment', 'handleDeleteComment'] as $handler) {
            $this->dispatcher->{$handler}(404);
        }

        expect($this->log)->toHaveCount(0);
    });
});

describe('BlogEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(BlogEventDispatcher::class);
        $this->site = new FakeSite(8);

        Functions\when('get_site')->alias(fn (int $id): ?object => $id === 8 ? $this->site : null);
    });

    it('dispatches BlogCreated with the new site and the initialisation arguments', function (): void {
        $this->dispatcher->handleWpInitializeSite($this->site, ['title' => 'My blog']);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(BlogCreated::class)
            ->and($this->log[0]->site)->toBe($this->site)
            ->and($this->log[0]->args)->toBe(['title' => 'My blog']);
    });

    it('dispatches BlogDeleted with the site WordPress deletes', function (): void {
        $this->dispatcher->handleWpDeleteSite($this->site);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(BlogDeleted::class)
            ->and($this->log[0]->site)->toBe($this->site);
    });

    it('maps each blog status hook to its event with the loaded site', function (string $handler, string $expected): void {
        $this->dispatcher->{$handler}(8);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->site)->toBe($this->site);
    })->with([
        'archive_blog' => ['handleArchiveBlog', BlogArchived::class],
        'unarchive_blog' => ['handleUnarchiveBlog', BlogUnarchived::class],
        'make_spam_blog' => ['handleMakeSpamBlog', BlogMarkedAsSpam::class],
        'make_ham_blog' => ['handleMakeHamBlog', BlogMarkedAsNotSpam::class],
        'mature_blog' => ['handleMatureBlog', BlogMarkedAsMature::class],
        'unmature_blog' => ['handleUnmatureBlog', BlogMarkedAsNotMature::class],
        'make_delete_blog' => ['handleMakeDeleteBlog', BlogTrashed::class],
        'make_undelete_blog' => ['handleMakeUndeleteBlog', BlogRestored::class],
    ]);

    it('dispatches BlogVisibilityUpdated with the new visibility value', function (): void {
        $this->dispatcher->handleUpdateBlogPublic(8, '0');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(BlogVisibilityUpdated::class)
            ->and($this->log[0]->site)->toBe($this->site)
            ->and($this->log[0]->visibility)->toBe('0');
    });

    it('ignores a site that does not exist', function (): void {
        foreach (['handleArchiveBlog', 'handleUnarchiveBlog', 'handleMakeSpamBlog', 'handleMakeHamBlog', 'handleMatureBlog', 'handleUnmatureBlog', 'handleMakeDeleteBlog', 'handleMakeUndeleteBlog'] as $handler) {
            $this->dispatcher->{$handler}(404);
        }
        $this->dispatcher->handleUpdateBlogPublic(404, '1');

        expect($this->log)->toHaveCount(0);
    });
});
