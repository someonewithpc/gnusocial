<?php

declare(strict_types = 1);

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

/**
 * ActivityPub implementation for GNU social
 *
 * @package   GNUsocial
 * @category  ActivityPub
 *
 * @author    Diogo Peralta Cordeiro <@diogo.site>
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */

namespace Plugin\ActivityPub\Util\Model;

use ActivityPhp\Type;
use ActivityPhp\Type\AbstractObject;
use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Event;
use App\Core\GSFile;
use App\Core\HTTPClient;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Router\Router;
use App\Core\VisibilityScope;
use App\Entity\Note as GSNote;
use App\Entity\NoteType;
use App\Util\Common;
use App\Util\Exception\ClientException;
use App\Util\Exception\DuplicateFoundException;
use App\Util\Exception\NoSuchActorException;
use App\Util\Exception\ServerException;
use App\Util\Formatting;
use App\Util\HTML;
use App\Util\TemporaryFile;
use Component\Attachment\Entity\ActorToAttachment;
use Component\Attachment\Entity\AttachmentToNote;
use Component\Conversation\Conversation;
use Component\FreeNetwork\FreeNetwork;
use Component\Language\Entity\Language;
use Component\Notification\Entity\Attention;
use Component\Tag\Entity\NoteTag;
use Component\Tag\Tag;
use DateTime;
use DateTimeInterface;
use Exception;
use InvalidArgumentException;
use Plugin\ActivityPub\ActivityPub;
use Plugin\ActivityPub\Entity\ActivitypubObject;
use Plugin\ActivityPub\Util\Explorer;
use Plugin\ActivityPub\Util\Model;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * This class handles translation between JSON and GSNotes
 *
 * @copyright 2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Note extends Model
{
    /**
     * Create an Entity from an ActivityStreams 2.0 JSON string
     * This will persist a new GSNote
     *
     * @throws ClientException
     * @throws ClientExceptionInterface
     * @throws DuplicateFoundException
     * @throws NoSuchActorException
     * @throws RedirectionExceptionInterface
     * @throws ServerException
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public static function fromJson(string|AbstractObject $json, array $options = []): GSNote
    {
        $handleInReplyTo = function (AbstractObject|string $type_note): ?int {
            try {
                $parent_note = \is_null($type_note->get('inReplyTo')) ? null : ActivityPub::getObjectByUri($type_note->get('inReplyTo'), try_online: true);
                if ($parent_note instanceof GSNote) {
                    return $parent_note->getId();
                } elseif ($parent_note instanceof Type\AbstractObject && $parent_note->get('type') === 'Note') {
                    return self::fromJson($parent_note)->getId();
                } else {
                    return null;
                }
            } catch (Exception $e) {
                Log::debug('ActivityStreams:Model:Note-> An error occurred retrieving parent note.', [$e]);
                // Sadly we won't be able to have this note inside the correct conversation for now.
                // TODO: Create an entity that registers notes falsely without parent so, when the parent is retrieved,
                // we can update the child with the correct parent.
                return null;
            }
        };

        $source    = $options['source'] ?? 'ActivityPub';
        $type_note = \is_string($json) ? self::jsonToType($json) : $json;
        $actor_id  = null;
        $actor     = null;
        $to        = $type_note->has('to') ? (\is_string($type_note->get('to')) ? [$type_note->get('to')] : $type_note->get('to')) : [];
        $cc        = $type_note->has('cc') ? (\is_string($type_note->get('cc')) ? [$type_note->get('cc')] : $type_note->get('cc')) : [];
        if ($json instanceof AbstractObject
            && \array_key_exists('test_authority', $options)
            && $options['test_authority']
            && \array_key_exists('actor_uri', $options)
        ) {
            $actor_uri = $options['actor_uri'];
            if ($actor_uri !== $type_note->get('attributedTo')) {
                if (parse_url($actor_uri)['host'] !== parse_url($type_note->get('attributedTo'))['host']) {
                    throw new Exception('You don\'t seem to have enough authority to create this note.');
                }
            } else {
                $actor    = $options['actor']    ?? null;
                $actor_id = $options['actor_id'] ?? $actor?->getId();
            }
        }

        if (\is_null($actor_id)) {
            $actor    = ActivityPub::getActorByUri($type_note->get('attributedTo'));
            $actor_id = $actor->getId();
        }
        $map = [
            'is_local'     => false,
            'created'      => new DateTime($type_note->get('published') ?? 'now'),
            'content'      => $type_note->get('content') ?? null,
            'rendered'     => \is_null($type_note->get('content')) ? null : HTML::sanitize($type_note->get('content')),
            'title'        => $type_note->get('name') ?? null,
            'content_type' => 'text/html',
            'language_id'  => $type_note->get('contentLang') ?? null,
            'url'          => $type_note->get('url') ?? $type_note->get('id'),
            'actor_id'     => $actor_id,
            'reply_to'     => $reply_to = $handleInReplyTo($type_note),
            'modified'     => new DateTime(),
            'type'         => match ($type_note->get('type')) {
                'Page'     => NoteType::PAGE, default => NoteType::NOTE
            },
            'source' => $source,
        ];

        if (!\is_null($map['language_id'])) {
            $map['language_id'] = Language::getByLocale($map['language_id'])->getId();
        } else {
            $map['language_id'] = null;
        }

        // Scope
        if (\in_array('https://www.w3.org/ns/activitystreams#Public', $to)) {
            // Public: Visible for all, shown in public feeds
            $map['scope'] = VisibilityScope::EVERYWHERE;
        } elseif (\in_array('https://www.w3.org/ns/activitystreams#Public', $cc)) {
            // Unlisted: Visible for all but not shown in public feeds
            // It isn't the note that dictates what feed is shown in but the feed, it only dictates who can access it.
            $map['scope'] = 'unlisted';
        } else {
            // Either Followers-only or Direct
            if ($type_note->get('type') === 'ChatMessage' // Is DM explicitly?
            || (empty($type_note->get('cc')))) { // Only has TO targets
                $map['scope'] = VisibilityScope::MESSAGE;
            } else { // Then is collection
                $map['scope'] = VisibilityScope::COLLECTION;
            }
        }

        $object_mentions_ids = [];
        foreach ($to as $target) {
            if ($target === 'https://www.w3.org/ns/activitystreams#Public') {
                continue;
            }
            try {
                $actor                                = ActivityPub::getActorByUri($target);
                $object_mentions_ids[$actor->getId()] = $target;
                // If $to is a group and note is unlisted, set note's scope as Group
                if ($actor->isGroup() && $map['scope'] === 'unlisted') {
                    $map['scope'] = VisibilityScope::GROUP;
                }
            } catch (Exception $e) {
                Log::debug('ActivityPub->Model->Note->fromJson->getActorByUri', [$e]);
            }
        }

        // We can drop this insight already
        if ($map['scope'] === 'unlisted') {
            $map['scope'] = VisibilityScope::EVERYWHERE;
        }

        foreach ($cc as $target) {
            if ($target === 'https://www.w3.org/ns/activitystreams#Public') {
                continue;
            }
            try {
                $actor                                = ActivityPub::getActorByUri($target);
                $object_mentions_ids[$actor->getId()] = $target;
            } catch (Exception $e) {
                Log::debug('ActivityPub->Model->Note->fromJson->getActorByUri', [$e]);
            }
        }

        $obj = GSNote::create($map);

        // Attachments
        $processed_attachments = [];
        foreach ($type_note->get('attachment') ?? [] as $attachment) {
            if ($attachment->get('type') === 'Document') {
                // Retrieve media
                $get_response = HTTPClient::get($attachment->get('url'));
                $media        = $get_response->getContent();
                unset($get_response);
                // Ignore empty files
                if (!empty($media)) {
                    // Create an attachment for this
                    $temp_file = new TemporaryFile();
                    $temp_file->write($media);
                    $filesize      = $temp_file->getSize();
                    $max_file_size = Common::getUploadLimit();
                    if ($max_file_size < $filesize) {
                        throw new ClientException(_m('No file may be larger than {quota} bytes and the file you sent was {size} bytes. '
                            . 'Try to upload a smaller version.', ['quota' => $max_file_size, 'size' => $filesize], ));
                    }
                    Event::handle('EnforceUserFileQuota', [$filesize, $actor_id]);

                    $processed_attachments[] = [GSFile::storeFileAsAttachment($temp_file), $attachment->get('name')];
                }
            }
        }

        DB::persist($obj);

        // Assign conversation to this note
        Conversation::assignLocalConversation($obj, $reply_to);

        foreach ($type_note->get('tag') ?? [] as $ap_tag) {
            switch ($ap_tag->get('type')) {
                case 'Mention':
                    $explorer = new Explorer();
                    try {
                        $actors = $explorer->lookup($ap_tag->get('href'));
                        foreach ($actors as $actor) {
                            $object_mentions_ids[$actor->getId()] = $ap_tag->get('href');
                        }
                    } catch (Exception $e) {
                        Log::debug('ActivityPub->Model->Note->fromJson->Mention->Explorer', [$e]);
                    }
                    break;
                case 'Hashtag':
                    $match         = ltrim($ap_tag->get('name'), '#');
                    $tag           = Tag::extract($match);
                    $canonical_tag = $ap_tag->get('canonical') ?? Tag::canonicalTag($tag, \is_null($lang_id = $obj->getLanguageId()) ? null : Language::getById($lang_id)->getLocale());
                    DB::persist(NoteTag::create([
                        'tag'           => $tag,
                        'canonical'     => $canonical_tag,
                        'note_id'       => $obj->getId(),
                        'use_canonical' => $ap_tag->get('canonical') ?? false,
                        'language_id'   => $lang_id ?? null,
                    ]));
                    Cache::pushList("tag-{$canonical_tag}", $obj);
                    foreach (Tag::cacheKeys($canonical_tag) as $key) {
                        Cache::delete($key);
                    }
                    break;
            }
        }

        // The content would be non-sanitized text/html
        Event::handle('ProcessNoteContent', [$obj, $obj->getRendered(), $obj->getContentType(), $process_note_content_extra_args = ['TagProcessed' => true, 'ignoreLinks' => $object_mentions_ids]]);

        $object_mentions_ids = array_keys($object_mentions_ids);
        $obj->setObjectMentionsIds($object_mentions_ids);

        if ($processed_attachments !== []) {
            foreach ($processed_attachments as [$a, $fname]) {
                if (DB::count('actor_to_attachment', $args = ['attachment_id' => $a->getId(), 'actor_id' => $actor_id]) === 0) {
                    DB::persist(ActorToAttachment::create($args));
                }
                DB::persist(AttachmentToNote::create(['attachment_id' => $a->getId(), 'note_id' => $obj->getId(), 'title' => $fname]));
            }
        }

        $map = [
            'object_uri'  => $type_note->get('id'),
            'object_type' => 'note',
            'object_id'   => $obj->getId(),
            'created'     => new DateTime($type_note->get('published') ?? 'now'),
            'modified'    => new DateTime(),
        ];
        $ap_obj = new ActivitypubObject();
        foreach ($map as $prop => $val) {
            $set = Formatting::snakeCaseToCamelCase("set_{$prop}");
            $ap_obj->{$set}($val);
        }
        DB::persist($ap_obj);

        return $obj;
    }

    /**
     * Get a JSON
     *
     * @throws Exception
     */
    public static function toJson(mixed $object, ?int $options = null): string
    {
        if ($object::class !== GSNote::class) {
            throw new InvalidArgumentException('First argument type must be a Note.');
        }

        $attr = [
            '@context'         => ActivityPub::$activity_streams_two_context,
            'type'             => $object->getScope() === VisibilityScope::MESSAGE ? 'ChatMessage' : (match ($object->getType()) {
                NoteType::NOTE => 'Note', NoteType::PAGE => 'Page'
            }),
            'id'             => $object->getUrl(),
            'published'      => $object->getCreated()->format(DateTimeInterface::RFC3339),
            'attributedTo'   => $object->getActor()->getUri(Router::ABSOLUTE_URL),
            'name'           => $object->getTitle(),
            'content'        => $object->getRendered(),
            'mediaType'      => 'text/html',
            'source'         => ['content' => $object->getContent(), 'mediaType' => $object->getContentType()],
            'attachment'     => [],
            'tag'            => [],
            'inReplyTo'      => \is_null($object->getReplyTo()) ? null : ActivityPub::getUriByObject(GSNote::getById($object->getReplyTo())),
            'inConversation' => $object->getConversationUri(),
        ];

        // Target scope
        switch ($object->getScope()) {
            case VisibilityScope::EVERYWHERE:
                $attr['to'] = ['https://www.w3.org/ns/activitystreams#Public'];
                $attr['cc'] = [Router::url('actor_subscribers_id', ['id' => $object->getActor()->getId()], Router::ABSOLUTE_URL)];
            break;
            case VisibilityScope::LOCAL:
                throw new ClientException('This note was not federated.', 403);
            case VisibilityScope::ADDRESSEE:
            case VisibilityScope::MESSAGE:
                $attr['to'] = []; // Will be filled later
                $attr['cc'] = [];
                break;
            case VisibilityScope::GROUP:
                // Will have the group in the To coming from attentions
            case VisibilityScope::COLLECTION:
                // Since we don't support sending unlisted/followers-only
                // notices, arriving here means we're instead answering to that type
                // of posts. In this situation, it's safer to always send answers of type unlisted.
                $attr['to'] = [];
                $attr['cc'] = ['https://www.w3.org/ns/activitystreams#Public'];
                break;
            default:
                Log::error('ActivityPub->Note->toJson: Found an unknown visibility scope.');
                throw new ServerException('Found an unknown visibility scope which cannot federate.');
        }

        $attention_cc = DB::findBy(Attention::class, ['note_id' => $object->getId()]);
        foreach ($attention_cc as $cc_id) {
            $target = \App\Entity\Actor::getById($cc_id->getTargetId());
            if ($object->getScope() === VisibilityScope::GROUP && $target->isGroup()) {
                $attr['to'][] = $target->getUri(Router::ABSOLUTE_URL);
            } else {
                $attr['cc'][] = $target->getUri(Router::ABSOLUTE_URL);
            }
        }

        // Mentions
        foreach ($object->getNotificationTargets() as $mention) {
            $attr['tag'][] = [
                'type' => 'Mention',
                'href' => ($href = $mention->getUri()),
                'name' => $mention->isGroup() ? FreeNetwork::groupTagToName($mention->getNickname(), $href) : FreeNetwork::mentionTagToName($mention->getNickname(), $href),
            ];
            $attr['to'][] = $href;
        }

        // Hashtags
        foreach ($object->getTags() as $hashtag) {
            $attr['tag'][] = [
                'type'      => 'Hashtag',
                'href'      => $hashtag->getUrl(type: Router::ABSOLUTE_URL),
                'name'      => "#{$hashtag->getTag()}",
                'canonical' => $hashtag->getCanonical(),
            ];
        }

        // Attachments
        foreach ($object->getAttachments() as $attachment) {
            $attr['attachment'][] = [
                'type'      => 'Document',
                'mediaType' => $attachment->getMimetype(),
                'url'       => $attachment->getUrl(note: $object, type: Router::ABSOLUTE_URL),
                'name'      => DB::findOneBy(AttachmentToNote::class, ['attachment_id' => $attachment->getId(), 'note_id' => $object->getId()], return_null: true)?->getTitle(),
                'width'     => $attachment->getWidth(),
                'height'    => $attachment->getHeight(),
            ];
        }

        $type = self::jsonToType($attr);
        Event::handle('ActivityPubAddActivityStreamsTwoData', [$type->get('type'), &$type]);
        return $type->toJson($options);
    }
}
