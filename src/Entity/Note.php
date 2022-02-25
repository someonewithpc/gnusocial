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

namespace App\Entity;

use App\Core\ActorLocalRoles;
use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\Event;
use function App\Core\I18n\_m;
use App\Core\Log;
use App\Core\Router\Router;
use App\Core\VisibilityScope;
use App\Util\Exception\ClientException;
use App\Util\Exception\NoSuchNoteException;
use App\Util\Formatting;
use Component\Avatar\Avatar;
use Component\Conversation\Entity\Conversation;
use Component\Language\Entity\Language;
use Component\Notification\Entity\Attention;
use DateTimeInterface;
use function mb_substr;
use const PREG_SPLIT_NO_EMPTY;

// The domain of this enum are Notes
enum NoteType : int // having an int is just convenient
{
    case NOTE = 1;  // Is an element of microblogging, a direct message, or a reply to another note or page
    case PAGE = 2;  // Larger content note, beginning of a thread, or an email message
}

/**
 * Entity for notices
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Note extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private int $actor_id;
    private ?string $content     = null;
    private string $content_type = 'text/plain';
    private ?string $rendered    = null;
    private int $conversation_id;
    private ?int $reply_to = null;
    private bool $is_local;
    private ?string $source   = null;
    private int $scope        = 1;  //VisibilityScope::EVERYWHERE->value;
    private ?string $url      = null;
    private ?int $language_id = null;
    private int $type         = 1;  //NoteType::NOTE->value;
    private ?string $title    = null;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setActorId(int $actor_id): self
    {
        $this->actor_id = $actor_id;
        return $this;
    }

    public function getActorId(): int
    {
        return $this->actor_id;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContentType(string $content_type): self
    {
        $this->content_type = mb_substr($content_type, 0, 129);
        return $this;
    }

    public function getContentType(): string
    {
        return $this->content_type;
    }

    public function setRendered(?string $rendered): self
    {
        $this->rendered = $rendered;
        return $this;
    }

    public function getRendered(): ?string
    {
        return $this->rendered;
    }

    public function setConversationId(int $conversation_id): self
    {
        $this->conversation_id = $conversation_id;
        return $this;
    }

    public function getConversationId(): int
    {
        return $this->conversation_id;
    }

    public function setReplyTo(?int $reply_to): self
    {
        $this->reply_to = $reply_to;
        return $this;
    }

    public function getReplyTo(): ?int
    {
        return $this->reply_to;
    }

    public function setIsLocal(bool $is_local): self
    {
        $this->is_local = $is_local;
        return $this;
    }

    public function getIsLocal(): bool
    {
        return $this->is_local;
    }

    public function setSource(?string $source): self
    {
        $this->source = \is_null($source) ? null : mb_substr($source, 0, 32);
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setScope(VisibilityScope|int $scope): self
    {
        $this->scope = \is_int($scope) ? $scope : $scope->value;
        return $this;
    }

    public function getScope(): VisibilityScope
    {
        return VisibilityScope::from($this->scope);
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setLanguageId(?int $language_id): self
    {
        $this->language_id = $language_id;
        return $this;
    }

    public function getLanguageId(): ?int
    {
        return $this->language_id;
    }

    public function setType(NoteType|int $type): self
    {
        $this->type = \is_int($type) ? $type : $type->value;
        return $this;
    }

    public function getType(): NoteType
    {
        return NoteType::from($this->type);
    }

    public function setTitle(?string $title): self
    {
        $this->title = \is_null($title) ? null : mb_substr($title, 0, 129);
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function cacheKeys(int $note_id)
    {
        return [
            'note'              => "note-{$note_id}",
            'attachments'       => "note-attachments-{$note_id}",
            'attachments-title' => "note-attachments-with-title-{$note_id}",
            'links'             => "note-links-{$note_id}",
            'tags'              => "note-tags-{$note_id}",
            'replies'           => "note-replies-{$note_id}",
        ];
    }

    public function getConversation(): Conversation
    {
        return Conversation::getByPK(['id' => $this->getConversationId()]);
    }

    public function getConversationUrl(int $type = Router::ABSOLUTE_URL): ?string
    {
        return Router::url('conversation', ['conversation_id' => $this->getConversationId()], $type);
    }

    public function getConversationUri(): string
    {
        return $this->getConversationUrl(type: Router::ABSOLUTE_URL);
    }

    public function getActor(): Actor
    {
        return Actor::getById($this->getActorId());
    }

    public function getActorNickname(): string
    {
        return Actor::getNicknameById($this->getActorId());
    }

    public function getActorFullname(): ?string
    {
        return Actor::getFullnameById($this->getActorId());
    }

    public function getActorAvatarUrl(string $size = 'medium'): string
    {
        return Avatar::getUrl($this->getActorId(), $size);
    }

    public static function getById(int $note_id): self
    {
        return Cache::get(self::cacheKeys($note_id)['note'], fn () => DB::findOneBy('note', ['id' => $note_id]));
    }

    public function getNoteLanguageShortDisplay(): ?string
    {
        return !\is_null($this->getLanguageId()) ? Language::getById($this->getLanguageId())->getShortDisplay() : null;
    }

    public function getLanguageLocale(): ?string
    {
        return !\is_null($this->getLanguageId()) ? Language::getById($this->getLanguageId())->getLocale() : null;
    }

    public function getRenderedSplit(): array
    {
        return preg_split('/(<\s*p\s*\/?>)|(<\s*br\s*\/?>)|(\s\s+)|(<\s*\/p\s*\/?>)/', $this->getRendered(), -1, PREG_SPLIT_NO_EMPTY);
    }

    public static function getAllNotesByActor(Actor $actor): array
    {
        return DB::findBy(self::class, ['actor_id' => $actor->getId()], order_by: ['created' => 'DESC', 'id' => 'DESC']);
    }

    public function getAttachments(): array
    {
        return Cache::getList(self::cacheKeys($this->getId())['attachments'], function () {
            return DB::dql(
                <<<'EOF'
                    select att from attachment att
                        join attachment_to_note atn with atn.attachment_id = att.id
                    where atn.note_id = :note_id
                    EOF,
                ['note_id' => $this->id],
            );
        });
    }

    public function getAttachmentsWithTitle(): array
    {
        return Cache::getList(self::cacheKeys($this->getId())['attachments-title'], function () {
            $from_db = DB::dql(
                <<<'EOF'
                    select att, atn.title
                    from attachment att
                        join attachment_to_note atn with atn.attachment_id = att.id
                    where atn.note_id = :note_id
                    EOF,
                ['note_id' => $this->id],
            );
            $results = [];
            foreach ($from_db as $fd) {
                $results[] = [$fd[0], $fd['title']];
            }
            return $results;
        });
    }

    public function getLinks(): array
    {
        return Cache::getList(self::cacheKeys($this->getId())['links'], function () {
            return DB::dql(
                <<<'EOF'
                    select l from link l
                        join note_to_link ntl with ntl.link_id = l.id
                    where ntl.note_id = :note_id
                    EOF,
                ['note_id' => $this->id],
            );
        });
    }

    public function getTags(): array
    {
        return Cache::getList(self::cacheKeys($this->getId())['tags'], fn () => DB::findBy('note_tag', ['note_id' => $this->getId()]));
    }

    /**
     * Returns this Note's reply_to/parent.
     *
     * If we don't know the reply, we might know the **Conversation**!
     * This will happen if a known remote user replies to an
     * unknown remote user - within a known Conversation.
     *
     * As such, **do not take for granted** that this is a root
     * Note of a Conversation, in case this returns null!
     * Whenever a note is created, checks should be made
     * to guarantee that the latest info is correct.
     */
    public function getReplyToNote(): ?self
    {
        return \is_null($this->getReplyTo()) ? null : self::getById($this->getReplyTo());
    }

    /**
     * Returns all **known** replies made to this entity
     */
    public function getReplies(): array
    {
        return Cache::getList(self::cacheKeys($this->getId())['replies'], fn () => DB::findBy('note', ['reply_to' => $this->getId()], order_by: ['created' => 'DESC', 'id' => 'DESC']));
    }

    /**
     * Whether this note is visible to the given actor
     */
    public function isVisibleTo(null|Actor|LocalUser $actor, ?Actor $in = null): bool
    {
        // TODO: cache this
        switch ($this->getScope()) {
            case VisibilityScope::LOCAL: // The controller handles it if private
            case VisibilityScope::EVERYWHERE:
                return true;
            case VisibilityScope::ADDRESSEE:
                // If the actor is logged in and
                return (bool) (!\is_null($actor)
                    && (
                        // Is either the author Or
                        $this->getActorId() == $actor->getId()
                        // one of the targets
                        || \in_array($actor->getId(), $this->getNotificationTargetIds())
                    ));
            case VisibilityScope::GROUP:
                if (\is_null($in)) {
                    return false; // If we don't have a context, don't risk leaking this note.
                }
                // Only for the group to see
                return !\is_null($actor) && (
                    !($in->getRoles() & ActorLocalRoles::PRIVATE_GROUP) // Public Group
                        || DB::dql( // It's a member of the private group
                            <<<'EOF'
                                SELECT m FROM \Component\Group\Entity\GroupMember m
                                	JOIN \Component\Notification\Entity\Notification att WITH m.group_id = att.target_id
                                	JOIN \App\Entity\Activity a WITH att.activity_id = a.id
                                WHERE a.object_id = :note_id AND m.actor_id = :actor_id
                                EOF,
                            ['note_id' => $this->id, 'actor_id' => $in->getId()],
                        ) !== []
                );
            case VisibilityScope::COLLECTION:
            case VisibilityScope::MESSAGE:
                // Only for the collection to see
                return !\is_null($actor) && \in_array($actor->getId(), $this->getNotificationTargetIds());
            default:
                Log::error("Unknown scope found: {$this->getScope()->value}.");
        }
        return false;
    }

    // @return array of ids of Actors
    public array $_object_mentions_ids = [];

    public function setObjectMentionsIds(array $mentions): self
    {
        $this->_object_mentions_ids = $mentions;
        return $this;
    }

    public function getAttentionTargetIds(?int $sender_id = null): array
    {
        $attentioned = [];
        $attention_cc = DB::findBy(Attention::class, ['note_id' => $this->getId()]);
        foreach ($attention_cc as $cc) {
            $cc_id = $cc->getTargetId();
            if ($cc_id === $sender_id) {
                continue;
            }
            $attentioned[] = $cc_id;
        }
        return $attentioned;
    }

    public function getMentionTargetIds(): array
    {
        $target_ids = [];
        $content = $this->getContent();
        if (!\is_null($content)) {
            $mentions = Formatting::findMentions($content, $this->getActor());
            foreach ($mentions as $mention) {
                foreach ($mention['mentioned'] as $m) {
                    $target_ids[] = $m->getId();
                }
            }
        }
        return $target_ids;
    }

    /**
     * @see Entity->getNotificationTargetIds
     */
    public function getNotificationTargetIds(array $ids_already_known = [], ?int $sender_id = null, bool $include_additional = true): array
    {
        $target_ids = $this->_object_mentions_ids ?? [];

        // Parent
        if (!\array_key_exists('object-related', $ids_already_known)) {
            if (!\is_null($parent = $this->getReplyToNote())) {
                $target_ids[] = $parent->getActorId();
                array_push($target_ids, ...$parent->getNotificationTargetIds());
            }
        } else {
            array_push($target_ids, ...$ids_already_known['object-related']);
        }

        // Mentions
        if (!\array_key_exists('object', $ids_already_known)) {
            array_push($target_ids, ...$this->getMentionTargetIds());
        } else {
            array_push($target_ids, ...$ids_already_known['object']);
        }

        // Attentions
        if (!\array_key_exists('note-attention', $ids_already_known)) {
            array_push($target_ids, ...$this->getAttentionTargetIds($sender_id));
        } else {
            array_push($target_ids, ...$ids_already_known['note-attention']);
        }

        // Additional actors that should know about this
        if ($include_additional && \array_key_exists('additional', $ids_already_known)) {
            array_push($target_ids, ...$ids_already_known['additional']);
        }

        return array_unique($target_ids);
    }

    public function getAttentionTargets(?int $sender_id = null): array
    {
        $attentioned = $this->getAttentionTargetIds();
        return DB::findBy('actor', ['id' => $attentioned]);
    }

    public function getMentionTargets(): array
    {
        $mentioned = [];
        $mentions = Formatting::findMentions($this->getContent(), $this->getActor());
        foreach ($mentions as $mention) {
            foreach ($mention['mentioned'] as $m) {
                $mentioned[] = $m;
            }
        }
        return $mentioned;
    }

    /**
     * @return array of Actors
     */
    public function getNotificationTargets(array $ids_already_known = [], ?int $sender_id = null, bool $include_additional = true): array
    {
        // Additional (if we have additional, we will just return all the actors from ids)
        if ($include_additional && \array_key_exists('additional', $ids_already_known)) {
            $target_ids = $this->getNotificationTargetIds($ids_already_known, $sender_id);
            return $target_ids === [] ? [] : DB::findBy(Actor::class, ['id' => $target_ids]);
        }

        $targets = $this->_object_mentions_ids === [] ? [] : DB::findBy(Actor::class, ['id' => $this->_object_mentions_ids]);

        // Parent
        if (!\array_key_exists('object-related', $ids_already_known)) {
            if (!\is_null($parent = $this->getReplyToNote())) {
                $targets[] = $parent->getActor();
                array_push($targets, ...$parent->getNotificationTargets());
            }
        } else {
            array_push($targets, ...$ids_already_known['object-related']);
        }

        // Mentions
        if (!\array_key_exists('object', $ids_already_known)) {
            array_push($targets, ...$this->getMentionTargets());
        } elseif ($ids_already_known['object'] !== []) {
            array_push($targets, ...DB::findBy('actor', ['id' => $ids_already_known['object']]));
        }

        // Attentions
        if (!\array_key_exists('note-attention', $ids_already_known)) {
            array_push($targets, ...$this->getAttentionTargets($sender_id));
        } else {
            $attentioned = $ids_already_known['note-attention'] ?? [];
            if ($attentioned !== []) {
                array_push($targets, ...DB::findBy('actor', ['id' => $attentioned]));
            }
        }

        return $targets;
    }

    public function delete(?Actor $actor = null, string $source = 'web'): Activity
    {
        Event::handle('NoteDeleteRelated', [&$this, $actor]);
        DB::persist($activity = Activity::create([
            'actor_id'    => $actor->getId(),
            'verb'        => 'delete',
            'object_type' => 'note',
            'object_id'   => $this->getId(),
            'source'      => $source,
        ]));
        DB::remove(DB::findOneBy(self::class, ['id' => $this->id]));
        return $activity;
    }

    public static function ensureCanInteract(?self $note, LocalUser|Actor $actor): self
    {
        if (\is_null($note)) {
            throw new NoSuchNoteException();
        } elseif (!$note->isVisibleTo($actor)) {
            throw new ClientException(_m('You don\'t have permissions to view this note.'), 401);
        } else {
            return $note;
        }
    }

    public static function schemaDef(): array
    {
        return [
            'name'   => 'note',
            'fields' => [
                'id'              => ['type' => 'serial', 'not null' => true],
                'actor_id'        => ['type' => 'int', 'foreign key' => true, 'target' => 'Actor.id', 'multiplicity' => 'one to one', 'not null' => true, 'description' => 'who made the note'],
                'content'         => ['type' => 'text', 'description' => 'note content'],
                'content_type'    => ['type' => 'varchar', 'not null' => true, 'default' => 'text/plain', 'length' => 129, 'description' => 'A note can be written in a multitude of formats such as text/plain, text/markdown, application/x-latex, and text/html'],
                'rendered'        => ['type' => 'text', 'description' => 'rendered note content, so we can keep the microtags (if not local)'],
                'conversation_id' => ['type' => 'serial', 'not null' => true, 'foreign key' => true, 'target' => 'Conversation.id', 'multiplicity' => 'one to one', 'description' => 'the conversation identifier'],
                'reply_to'        => ['type' => 'int', 'not null' => false, 'default' => null, 'foreign key' => true, 'target' => 'Note.id', 'multiplicity' => 'one to one', 'description' => 'note replied to, null if root of a conversation'],
                'is_local'        => ['type' => 'bool', 'not null' => true, 'description' => 'was this note generated by a local actor'],
                'source'          => ['type' => 'varchar', 'foreign key' => true, 'length' => 32, 'target' => 'NoteSource.code', 'multiplicity' => 'many to one', 'description' => 'fkey to source of note, like "web", "im", or "clientname"'],
                'scope'           => ['type' => 'int', 'not null' => true, 'default' => VisibilityScope::EVERYWHERE->value, 'description' => 'bit map for distribution scope; 1 = everywhere; 2 = this server only; 4 = addressees; 8 = groups; 16 = collection; 32 = messages'],
                'url'             => ['type' => 'text', 'description' => 'Permalink to Note'],
                'language_id'     => ['type' => 'int', 'foreign key' => true, 'target' => 'Language.id', 'multiplicity' => 'one to many', 'description' => 'The language for this note'],
                'type'            => ['type' => 'int', 'not null' => true, 'default' => NoteType::NOTE->value, 'description' => 'bit map for note type; 1 = Note; 2 = Page'],
                'title'           => ['type' => 'varchar', 'not null' => false, 'default' => null, 'length' => 129, 'description' => 'Title of a page or a note'],
                'created'         => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'        => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'indexes'     => [
                'note_created_id_is_local_idx'    => ['created', 'is_local'],
                'note_actor_created_idx'          => ['actor_id', 'created'],
                'note_is_local_created_actor_idx' => ['is_local', 'created', 'actor_id'],
                'note_reply_to_idx'               => ['reply_to'],
            ],
            'fulltext indexes' => ['notice_fulltext_idx' => ['content']], // TODO make this configurable
        ];
    }
}
