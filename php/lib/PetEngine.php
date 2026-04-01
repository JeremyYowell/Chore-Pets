<?php
/**
 * PetEngine – Calculates pet happiness state and naming eligibility.
 *
 * State is derived from recent chore completion rates so it feels
 * responsive to the child without being unfairly punishing.
 *
 * Calculation:
 *   • Look at the last 3 days of activity (excluding today if before noon).
 *   • For each day: (chores completed) / (total active chores) × 100.
 *   • Average those percentages → compare to PET_STATE_THRESHOLDS.
 *
 * Naming eligibility:
 *   • Requires NAMING_STREAK_DAYS (default 7) consecutive days at 100%.
 */
class PetEngine {

    /**
     * Returns one of: 'sick' | 'sad' | 'neutral' | 'happy' | 'thriving'
     */
    public static function getState(int $childId): string {
        $db = Database::get();

        // Count total active chores for this child
        $totalRow = Database::queryOne(
            "SELECT COUNT(*) AS cnt FROM chores WHERE child_id = ? AND active = 1",
            [$childId]
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        if ($total === 0) return 'neutral'; // no chores yet

        // Get last 3 calendar days (not including today to avoid mid-day penalty)
        $days = [];
        for ($i = 1; $i <= 3; $i++) {
            $days[] = date('Y-m-d', strtotime("-{$i} days"));
        }

        $percentages = [];
        foreach ($days as $day) {
            $row = Database::queryOne(
                "SELECT COUNT(*) AS done FROM chore_completions
                  WHERE child_id = ? AND completed_date = ?",
                [$childId, $day]
            );
            $done = (int)($row['done'] ?? 0);
            $percentages[] = ($done / $total) * 100;
        }

        $avg = array_sum($percentages) / count($percentages);

        $thresholds = PET_STATE_THRESHOLDS;
        arsort($thresholds); // highest first
        foreach ($thresholds as $state => $min) {
            if ($avg >= $min) return $state;
        }
        return 'sick';
    }

    /**
     * Returns the public image URL for a given pet type + state.
     */
    public static function getImageUrl(string $petType, string $state): string {
        return ASSETS_URL . "/pets/{$petType}-{$state}.png";
    }

    /**
     * Human-readable state description shown on screen.
     */
    public static function getStateLabel(string $state): string {
        return [
            'sick'     => 'Not feeling great 😷',
            'sad'      => 'Missing you 😢',
            'neutral'  => 'Doing okay 😐',
            'happy'    => 'Feeling good! 😊',
            'thriving' => 'Super happy! 🌟',
        ][$state] ?? 'Doing okay';
    }

    /**
     * Alexa speech equivalent (no emoji).
     */
    public static function getStateSpeech(string $childName, string $petName, string $state): string {
        $name = $petName ?: 'your pet';
        return [
            'sick'     => "{$name} is not feeling well. Complete your chores to help them feel better!",
            'sad'      => "{$name} misses you. Do your chores to cheer them up!",
            'neutral'  => "{$name} is doing okay. Keep up with your chores!",
            'happy'    => "{$name} is happy! Great job doing your chores!",
            'thriving' => "{$name} is super happy and thriving! You're doing an amazing job!",
        ][$state] ?? "{$name} is doing okay.";
    }

    /**
     * Check whether a child has earned the right to name their pet.
     * Requires NAMING_STREAK_DAYS consecutive days of 100% completion.
     * Sets naming_unlocked flag in DB if newly earned.
     */
    public static function checkNamingEligibility(int $childId): bool {
        // Already unlocked?
        $child = Database::queryOne(
            "SELECT naming_unlocked, pet_name FROM children WHERE id = ?",
            [$childId]
        );
        if (!$child) return false;
        if ($child['naming_unlocked']) return true;  // already unlocked

        $totalRow = Database::queryOne(
            "SELECT COUNT(*) AS cnt FROM chores WHERE child_id = ? AND active = 1",
            [$childId]
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        if ($total === 0) return false;

        $streakRequired = NAMING_STREAK_DAYS;
        for ($i = 1; $i <= $streakRequired; $i++) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $row = Database::queryOne(
                "SELECT COUNT(*) AS done FROM chore_completions
                  WHERE child_id = ? AND completed_date = ?",
                [$childId, $day]
            );
            if ((int)($row['done'] ?? 0) < $total) return false;
        }

        // Streak achieved – unlock naming
        Database::execute(
            "UPDATE children SET naming_unlocked = 1 WHERE id = ?",
            [$childId]
        );
        return true;
    }

    /**
     * Today's completion summary for a child: ['done' => N, 'total' => N]
     */
    public static function todayProgress(int $childId): array {
        $today = date('Y-m-d');
        $totalRow = Database::queryOne(
            "SELECT COUNT(*) AS cnt FROM chores WHERE child_id = ? AND active = 1",
            [$childId]
        );
        $total = (int)($totalRow['cnt'] ?? 0);

        $doneRow = Database::queryOne(
            "SELECT COUNT(*) AS cnt FROM chore_completions
              WHERE child_id = ? AND completed_date = ?",
            [$childId, $today]
        );
        $done = (int)($doneRow['cnt'] ?? 0);

        return ['done' => $done, 'total' => $total];
    }
}
