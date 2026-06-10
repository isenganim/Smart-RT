<?php

namespace App\Services;

use App\Models\Vote;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

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

        $phone = $lookup->resident->phone;

        if ($vote->ballots()->where('phone', $phone)->exists()) {
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        try {
            $ballot = DB::transaction(function () use ($vote, $optionId, $lookup, $phone) {
                $ballot = $vote->ballots()->create([
                    'vote_option_id' => $optionId,
                    'resident_id' => $lookup->resident->id,
                    'phone' => $phone,
                ]);

                Audit::record(null, 'vote.cast', 'vote_ballot', $ballot->id, [
                    'vote_id' => $vote->id,
                    'vote_option_id' => $optionId,
                    'resident_id' => $lookup->resident->id,
                ]);

                return $ballot;
            });
        } catch (UniqueConstraintViolationException) {
            return BallotResult::fail('Nomor HP ini sudah memberikan suara.');
        }

        return BallotResult::done($ballot);
    }

    public function tally(Vote $vote): array
    {
        $totals = $vote->ballots()
            ->selectRaw('vote_option_id, COUNT(*) as total')
            ->groupBy('vote_option_id')
            ->pluck('total', 'vote_option_id')
            ->map(fn ($total) => (int) $total);

        return $vote->options()
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => $totals->get($id, 0)])
            ->all();
    }
}
