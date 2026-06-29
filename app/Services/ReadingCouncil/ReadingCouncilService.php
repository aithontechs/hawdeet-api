<?php

namespace App\Services\ReadingCouncil;

use App\Models\{ReadingCouncil};
use App\Models\Admin;
use App\Models\Book;
use App\Models\ReadingCouncilMember;
use App\Models\User;


class ReadingCouncilService
{
    public function getAll(string $status = 'all', int $perPage = 10)
    {
        $query = ReadingCouncil::query()
            ->with('book:id,title,cover')
            ->withCount('members');

        match ($status) {
            'active'   => $query->active(),
            'upcoming' => $query->upcoming(),
            'closed'   => $query->closed(),
            default    => $query,
        };

        return $query->latest()->paginate($perPage);
    }

    public function getOne(ReadingCouncil $council, User $user)
    {
        $council->load([
            'book:id,title,cover,total_pages',
            'members' => fn($q) => $q->with('user:id,name,avatar_url')->latest()->limit(5),
        ]);

        $council->is_member = $council->isMember($user);

        $council->members_preview = $council->members->pluck('user')->values();
        $council->unsetRelation('members');
        return $council;
    }

    public function join(ReadingCouncil $council, User $user): void
    {
        abort_if($council->isClosed(), 403, 'This council is closed.');
        abort_if($council->isMember($user), 422, 'Already a member.');

        ReadingCouncilMember::create([
            'reading_council_id' => $council->id,
            'user_id'            => $user->id,
            'joined_at'          => now(),
        ]);

        $council->increment('members_count');
    }

    public function create(array $data, Admin|User $actor): ReadingCouncil
    {
        if ($actor instanceof User) {
            abort_unless($actor->is_author, 403, 'Only authors can create councils.');
            $book = Book::findOrFail($data['book_id']);
            abort_unless($book->author_id === $actor->id, 403, 'Not your book.');
        }

        $conuncil =  ReadingCouncil::create([
            ...$data,
            'admin_id'  => $actor instanceof Admin ? $actor->id : null,
            'author_id' => $actor instanceof User  ? $actor->id : null,
        ]);
        return $conuncil;
    }

    public function leave(ReadingCouncil $council, User $user): void
    {
        $member = ReadingCouncilMember::query()
            ->where('reading_council_id', $council->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $member->delete();
        $council->decrement('members_count');
    }

    public function getFeatured()
    {
        return ReadingCouncil::query()
                    ->with('book:id,title,cover')
                    ->active()
                    ->orderByDesc('members_count')
                    ->first();
    }

    public function update(ReadingCouncil $council, array $data, Admin|User $actor): ReadingCouncil
    {
        $this->authorizeActor($council, $actor);
        $council->update($data);
        return $council->fresh();
    }

    public function delete(ReadingCouncil $council, Admin|User $actor): void
    {
        $this->authorizeActor($council, $actor);
        $council->delete();
    }

    public function getMembers(ReadingCouncil $council, int $perPage = 20)
    {
        return $council->members()
            ->with('user:id,name,avatar_url')
            ->latest('joined_at')
            ->paginate($perPage);
    }

    private function authorizeActor(ReadingCouncil $council, Admin|User $actor): void
    {
        if ($actor instanceof Admin) return;

        abort_unless($actor instanceof User && $actor->is_author, 403, 'Unauthorized.');
        abort_unless($council->author_id === $actor->id, 403, 'Not your council.');
    }
}
