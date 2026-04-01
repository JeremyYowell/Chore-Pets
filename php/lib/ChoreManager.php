<?php
/**
 * ChoreManager – All CRUD operations for children, chores, and completions.
 */
class ChoreManager {

    // ── Households ─────────────────────────────────────────────────────────────

    public static function getOrCreateHousehold(string $alexaUserId): array {
        $row = Database::queryOne(
            "SELECT * FROM households WHERE alexa_user_id = ?",
            [$alexaUserId]
        );
        if ($row) return $row;

        $id = Database::execute(
            "INSERT INTO households (alexa_user_id) VALUES (?)",
            [$alexaUserId]
        );
        return ['id' => $id, 'alexa_user_id' => $alexaUserId, 'setup_done' => 0];
    }

    public static function markSetupDone(int $householdId): void {
        Database::execute(
            "UPDATE households SET setup_done = 1 WHERE id = ?",
            [$householdId]
        );
    }

    // ── Children ───────────────────────────────────────────────────────────────

    public static function getChildren(int $householdId): array {
        return Database::query(
            "SELECT * FROM children
              WHERE household_id = ? AND active = 1
              ORDER BY sort_order, id",
            [$householdId]
        );
    }

    public static function getChild(int $childId): ?array {
        return Database::queryOne(
            "SELECT * FROM children WHERE id = ? AND active = 1",
            [$childId]
        );
    }

    public static function findChildByName(int $householdId, string $name): ?array {
        return Database::queryOne(
            "SELECT * FROM children
              WHERE household_id = ? AND LOWER(name) = LOWER(?) AND active = 1",
            [$householdId, $name]
        );
    }

    public static function addChild(int $householdId, string $name, string $petType = 'cat'): int {
        $order = count(self::getChildren($householdId));
        return (int)Database::execute(
            "INSERT INTO children (household_id, name, pet_type, sort_order) VALUES (?, ?, ?, ?)",
            [$householdId, ucfirst(strtolower($name)), $petType, $order]
        );
    }

    public static function setPetType(int $childId, string $petType): void {
        if (!in_array($petType, PET_TYPES)) return;
        Database::execute(
            "UPDATE children SET pet_type = ? WHERE id = ?",
            [$petType, $childId]
        );
    }

    public static function setPetName(int $childId, string $name): bool {
        $child = self::getChild($childId);
        if (!$child || !$child['naming_unlocked']) return false;
        Database::execute(
            "UPDATE children SET pet_name = ? WHERE id = ?",
            [ucfirst(strtolower($name)), $childId]
        );
        return true;
    }

    // ── Chores ─────────────────────────────────────────────────────────────────

    public static function getChores(int $childId): array {
        return Database::query(
            "SELECT * FROM chores WHERE child_id = ? AND active = 1 ORDER BY sort_order, id",
            [$childId]
        );
    }

    public static function addChore(int $childId, string $name): int {
        $order = count(self::getChores($childId));
        return (int)Database::execute(
            "INSERT INTO chores (child_id, name, sort_order) VALUES (?, ?, ?)",
            [$childId, ucfirst($name), $order]
        );
    }

    public static function removeChore(int $choreId): void {
        Database::execute(
            "UPDATE chores SET active = 0 WHERE id = ?",
            [$choreId]
        );
    }

    // ── Completions ────────────────────────────────────────────────────────────

    /**
     * Returns chores with a 'completed_today' boolean for each.
     */
    public static function getChoresWithStatus(int $childId): array {
        $today = date('Y-m-d');
        return Database::query(
            "SELECT c.*,
                    (cc.id IS NOT NULL) AS completed_today
               FROM chores c
               LEFT JOIN chore_completions cc
                      ON cc.chore_id = c.id AND cc.completed_date = ?
              WHERE c.child_id = ? AND c.active = 1
              ORDER BY c.sort_order, c.id",
            [$today, $childId]
        );
    }

    /**
     * Toggle a chore: if not done today → mark done; if done → undo.
     * Pass $forceDone = true to always mark complete (never undo) — used for voice commands.
     * Returns true if the chore is now completed, false if uncompleted.
     */
    public static function toggleChore(int $choreId, int $childId, bool $forceDone = false): bool {
        $today = date('Y-m-d');
        $existing = Database::queryOne(
            "SELECT id FROM chore_completions
              WHERE chore_id = ? AND completed_date = ?",
            [$choreId, $today]
        );
        if ($existing) {
            if ($forceDone) return true; // already done, nothing to change
            Database::execute(
                "DELETE FROM chore_completions WHERE id = ?",
                [$existing['id']]
            );
            return false;
        } else {
            Database::execute(
                "INSERT INTO chore_completions (chore_id, child_id, completed_date)
                 VALUES (?, ?, ?)",
                [$choreId, $childId, $today]
            );
            return true;
        }
    }
}
