<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use Pollora\Events\WordPress\Media\MediaCreated;
use Pollora\Events\WordPress\Media\MediaDeleted;
use Pollora\Events\WordPress\Media\MediaEdited;
use Pollora\Events\WordPress\Media\MediaEventDispatcher;
use Pollora\Events\WordPress\Media\MediaUpdated;
use Pollora\Events\WordPress\Option\OptionEventDispatcher;
use Pollora\Events\WordPress\Option\OptionUpdated;
use Pollora\Events\WordPress\Post\PostCreated;
use Pollora\Events\WordPress\Post\PostDeleted;
use Pollora\Events\WordPress\Post\PostEventDispatcher;
use Pollora\Events\WordPress\Post\PostPublished;
use Pollora\Events\WordPress\Post\PostRestored;
use Pollora\Events\WordPress\Post\PostTrashed;
use Pollora\Events\WordPress\Post\PostUpdated;
use Tests\Unit\Events\Fixtures\FakePost;

use function Tests\Unit\Events\Fixtures\make_dispatcher;

require_once __DIR__.'/fixtures.php';

describe('PostEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(PostEventDispatcher::class);
        $this->post = new FakePost('the post');
    });

    it('maps each post status transition to the matching event', function (string $old, string $new, string $expected): void {
        $this->dispatcher->handleTransitionPostStatus($new, $old, $this->post);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->post)->toBe($this->post);
    })->with([
        'a new draft is a creation' => ['auto-draft', 'draft', PostCreated::class],
        'leaving the trash is a restoration' => ['trash', 'draft', PostRestored::class],
        'leaving the trash to publish is still a restoration' => ['trash', 'publish', PostRestored::class],
        'entering the trash is a trashing' => ['publish', 'trash', PostTrashed::class],
        'a draft going live is a publication' => ['draft', 'publish', PostPublished::class],
        'a scheduled post going live is a publication' => ['pending', 'publish', PostPublished::class],
        'a published post staying published is an update' => ['publish', 'publish', PostUpdated::class],
        'a scheduled post going live is an update' => ['future', 'publish', PostUpdated::class],
        'a draft becoming pending is an update' => ['draft', 'pending', PostUpdated::class],
    ]);

    it('hands the old and new status to the update event in that order', function (): void {
        $this->dispatcher->handleTransitionPostStatus('pending', 'draft', $this->post);

        expect($this->log[0])->toBeInstanceOf(PostUpdated::class)
            ->and($this->log[0]->oldStatus)->toBe('draft')
            ->and($this->log[0]->newStatus)->toBe('pending');
    });

    it('ignores auto-drafts, inherited statuses and revisions', function (string $new, string $postType): void {
        $this->dispatcher->handleTransitionPostStatus($new, 'draft', new FakePost('x', $postType));

        expect($this->log)->toHaveCount(0);
    })->with([
        'auto-draft' => ['auto-draft', 'post'],
        'inherit' => ['inherit', 'post'],
        'revision' => ['publish', 'revision'],
    ]);

    it('dispatches PostDeleted with the post WordPress still knows about', function (): void {
        Functions\when('get_post')->alias(fn (int $id): ?object => $id === 42 ? $this->post : null);

        $this->dispatcher->handleDeletedPost(42);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(PostDeleted::class)
            ->and($this->log[0]->post)->toBe($this->post);
    });

    it('ignores the deletion of an unknown post or of a revision', function (?string $postType): void {
        Functions\when('get_post')->justReturn($postType === null ? null : new FakePost('found', $postType));

        $this->dispatcher->handleDeletedPost(42);

        expect($this->log)->toHaveCount(0);
    })->with([
        'unknown' => [null],
        'revision' => ['revision'],
    ]);
});

describe('MediaEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(MediaEventDispatcher::class);
        $this->attachment = new FakePost('image', 'attachment');
    });

    it('maps each attachment hook to its event with the attachment', function (string $handler, string $expected): void {
        Functions\when('get_post')->alias(fn (int $id): ?object => $id === 7 ? $this->attachment : null);

        $this->dispatcher->{$handler}(7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf($expected)
            ->and($this->log[0]->attachment)->toBe($this->attachment);
    })->with([
        'add_attachment' => ['handleAddAttachment', MediaCreated::class],
        'edit_attachment' => ['handleEditAttachment', MediaUpdated::class],
        'delete_attachment' => ['handleDeleteAttachment', MediaDeleted::class],
    ]);

    it('ignores posts that are not attachments', function (string $handler): void {
        Functions\when('get_post')->justReturn(new FakePost('page', 'page'));

        $this->dispatcher->{$handler}(7);

        expect($this->log)->toHaveCount(0);
    })->with(['handleAddAttachment', 'handleEditAttachment', 'handleDeleteAttachment']);

    it('ignores an image edit of a post that is not an attachment', function (): void {
        Functions\when('get_post')->justReturn(new FakePost('page', 'page'));

        $this->dispatcher->handleImageEdit('', '/uploads/edited.jpg', 'image-data', 'image/jpeg', 7);

        expect($this->log)->toHaveCount(0);
    });

    it('ignores a missing attachment', function (): void {
        Functions\when('get_post')->justReturn(null);

        $this->dispatcher->handleAddAttachment(7);

        expect($this->log)->toHaveCount(0);
    });

    it('dispatches MediaEdited with the edited file name', function (): void {
        Functions\when('get_post')->alias(fn (int $id): ?object => $id === 7 ? $this->attachment : null);

        $this->dispatcher->handleImageEdit('', '/uploads/edited.jpg', 'image-data', 'image/jpeg', 7);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(MediaEdited::class)
            ->and($this->log[0]->attachment)->toBe($this->attachment)
            ->and($this->log[0]->filename)->toBe('/uploads/edited.jpg');
    });
});

describe('OptionEventDispatcher', function (): void {
    beforeEach(function (): void {
        [$this->dispatcher, $this->log] = make_dispatcher(OptionEventDispatcher::class);
    });

    it('dispatches OptionUpdated with the option name, old value and new value', function (): void {
        $this->dispatcher->handleUpdatedOption('blogname', 'Old', 'New');

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0])->toBeInstanceOf(OptionUpdated::class)
            ->and($this->log[0]->optionName)->toBe('blogname')
            ->and($this->log[0]->oldValue)->toBe('Old')
            ->and($this->log[0]->newValue)->toBe('New');
    });

    it('ignores options that are housekeeping rather than settings', function (string $option): void {
        $this->dispatcher->handleUpdatedOption($option, 'a', 'b');

        expect($this->log)->toHaveCount(0);
    })->with([
        'cron', 'doing_cron', '_transient_feed_123', '_site_transient_update_core', 'theme_mods_nectar',
    ]);

    it('only ignores options that start with an ignored prefix', function (): void {
        $this->dispatcher->handleUpdatedOption('my_cron', 'a', 'b');

        expect($this->log)->toHaveCount(1);
    });

    it('ignores an update that left the value strictly identical', function (): void {
        $this->dispatcher->handleUpdatedOption('blogname', 'same', 'same');

        expect($this->log)->toHaveCount(0);
    });

    it('treats a value of another type as a change', function (): void {
        $this->dispatcher->handleUpdatedOption('posts_per_page', '10', 10);

        expect($this->log)->toHaveCount(1)
            ->and($this->log[0]->oldValue)->toBe('10')
            ->and($this->log[0]->newValue)->toBe(10);
    });
});
