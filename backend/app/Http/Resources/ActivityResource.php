<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\HasBlameStamps;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One audit-trail row. The causer is rendered as `{ id, name }` like every other
 * blame stamp; the subject is a `{ type, id, label }` descriptor of the
 * User/Role that changed (null for non-model events such as a settings change),
 * and `properties` carries the old→new diff both the LogsActivity trait and
 * App\Support\ActivityLogger write.
 */
class ActivityResource extends JsonResource
{
    use HasBlameStamps;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'log_name' => $this->log_name,
            'event' => $this->event,
            'description' => $this->description,
            'subject' => $this->subjectPayload(),
            // Causer is always a user (or null when the actor was later deleted /
            // the write had no authenticated user). Shown as-is even to
            // non-super-admins, matching the created_by/updated_by convention.
            'causer' => $this->blameStamp($this->causer instanceof User ? $this->causer : null),
            'properties' => $this->diff(),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * The old→new change, normalized to `{ old, attributes }`. spatie v5 stores
     * the auto-logged model diff in `attribute_changes` and custom-supplied data
     * (our App\Support\ActivityLogger manual logs) in `properties`, so read from
     * whichever carries it.
     *
     * @return array{old: mixed, attributes: mixed}
     */
    private function diff(): array
    {
        $changes = $this->attribute_changes ?? collect();
        $props = $this->properties ?? collect();

        return [
            'old' => $changes->get('old') ?? $props->get('old'),
            'attributes' => $changes->get('attributes') ?? $props->get('attributes'),
        ];
    }

    /**
     * The subject descriptor, resilient to a since-deleted subject: type and id
     * come from the stored morph columns, and the label falls back to the diff'd
     * attributes when the model itself is gone (e.g. a `deleted` event).
     *
     * @return array{type: string, id: int|string|null, label: string|null}|null
     */
    private function subjectPayload(): ?array
    {
        if (! $this->subject_type) {
            return null;
        }

        $subject = $this->subject; // null if the record was deleted

        return [
            'type' => $this->morphAlias($this->subject_type),
            'id' => $this->subject_id,
            'label' => $subject ? $this->subjectLabel($subject) : $this->labelFromProperties(),
        ];
    }

    /** A short, stable alias for a morph class the frontend can switch on. */
    private function morphAlias(string $type): string
    {
        return match ($type) {
            User::class => 'user',
            Role::class => 'role',
            default => class_basename($type),
        };
    }

    private function subjectLabel(Model $subject): ?string
    {
        return match (true) {
            $subject instanceof User => $subject->name ?: $subject->email,
            $subject instanceof Role => $subject->name,
            default => null,
        };
    }

    /** Best-effort name for a subject whose model no longer exists. */
    private function labelFromProperties(): ?string
    {
        $diff = $this->diff();

        return $diff['attributes']['name']
            ?? $diff['old']['name']
            ?? null;
    }
}
