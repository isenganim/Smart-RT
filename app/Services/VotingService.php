<?php

namespace App\Services;

use App\Models\Vote;
use App\Support\PhoneNumber;
use Illuminate\Database\UniqueConstraintViolationException;

class VotingService
{
    public function __construct(protected ResidentLookup $lookup) {}

    public function cast(Vote $vote, int $optionId, ?string $rawPhone): BallotResult
    {
        if (! $vote->isOpen()) {
            return BallotResult::fail('Voting sudah ditutup.');
        }

        if (! $vote->options()->whereKey($optionId)->exists()) {
            return BallotResult::fail('Pilihan tidak valid.');
        }

        $lookup = $this->lookup->resolve($rawPhone);

        if (! $lookup->found()) {
            return BallotResult::fail($lookup->message);
        }

        $phone = PhoneNumber::normalize($rawPhone);

        if ($vote->ballots()->where('phone', $phone)->exists()) {
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        try {
            $ballot = $vote->ballots()->create([
                'vote_option_id' => $optionId,
                'resident_id' => $lookup->resident->id,
                'phone' => $phone,
            ]);
        } catch (UniqueConstraintViolationException) {
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        return BallotResult::done($ballot);
    }

    public function tally(Vote $vote): array
    {
        return $vote->ballots()
            ->selectRaw('vote_option_id, COUNT(*) as total')
            ->groupBy('vote_option_id')
            ->pluck('total', 'vote_option_id')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
