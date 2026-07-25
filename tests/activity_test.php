<?php
declare(strict_types=1);

/**
 * Plain-PHP CLI test for Erikr\Chrome\Admin\LogData's 'userId' filter and
 * Erikr\Chrome\Activity::render() — same style as tests/header_render_test.php
 * (no framework, no composer autoload, no live DB).
 *
 * Exercises LogData/Activity against a fake mysqli (subclasses the real
 * \mysqli / \mysqli_stmt but skips the native constructor, so no connection
 * is ever attempted) that records every prepared SQL string plus its bound
 * types/values — enough to assert LogData compiles an exact "l.idUser = ?"
 * comparison (not a LIKE) and that Activity always passes the caller-supplied
 * userId through untouched, regardless of $_GET content.
 *
 * Run: php tests/activity_test.php
 * Exit code 0 = all assertions passed, non-zero = at least one failure.
 */

require __DIR__ . '/../src/Admin/LogData.php';
require __DIR__ . '/../src/Activity.php';

use Erikr\Chrome\Admin\LogData;
use Erikr\Chrome\Activity;

// ── Fake mysqli / mysqli_stmt (no live DB) ───────────────────────────────

final class FakeMysqliStmt extends \mysqli_stmt
{
    public string $sql;
    public ?string $types = null;
    /** @var list<mixed> */
    public array $values = [];
    /** @var list<array<string,mixed>> */
    private array $rows;
    private int $countTotal;

    /** @param list<array<string,mixed>> $rows */
    public function __construct(string $sql, array $rows, int $countTotal)
    {
        $this->sql        = $sql;
        $this->rows       = $rows;
        $this->countTotal = $countTotal;
    }

    #[\ReturnTypeWillChange]
    public function bind_param($types, &...$vars)
    {
        $this->types  = $types;
        $this->values = $vars;
        return true;
    }

    #[\ReturnTypeWillChange]
    public function execute($params = null)
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function get_result()
    {
        $rows = $this->rows;
        return new class($rows) {
            private array $rows;
            private int $i = 0;
            public function __construct(array $rows) { $this->rows = $rows; }
            public function fetch_assoc()
            {
                if ($this->i >= count($this->rows)) {
                    return null;
                }
                return $this->rows[$this->i++];
            }
        };
    }

    #[\ReturnTypeWillChange]
    public function bind_result(&...$vars)
    {
        if (isset($vars[0])) {
            $vars[0] = $this->countTotal;
        }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function fetch()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        return true;
    }
}

final class FakeMysqli extends \mysqli
{
    /** @var list<FakeMysqliStmt> */
    public array $prepared = [];
    /** @var list<array<string,mixed>> */
    private array $rows;
    private int $countTotal;

    /** @param list<array<string,mixed>> $rows */
    public function __construct(array $rows = [], int $countTotal = 0)
    {
        $this->rows       = $rows;
        $this->countTotal = $countTotal;
    }

    #[\ReturnTypeWillChange]
    public function prepare($query)
    {
        $stmt = new FakeMysqliStmt($query, $this->rows, $this->countTotal);
        $this->prepared[] = $stmt;
        return $stmt;
    }
}

// ── Harness ────────────────────────────────────────────────────────────

$total  = 0;
$passed = 0;
$failures = [];

function check(bool $ok, string $label): void
{
    global $total, $passed, $failures;
    $total++;
    if ($ok) {
        $passed++;
    } else {
        $failures[] = $label;
    }
}

function assertContains(string $needle, string $haystack, string $label): void
{
    check(str_contains($haystack, $needle), $label . " (expected to contain: " . $needle . ")");
}

function assertNotContains(string $needle, string $haystack, string $label): void
{
    check(!str_contains($haystack, $needle), $label . " (expected NOT to contain: " . $needle . ")");
}

// ── 1. LogData: 'userId' filter compiles to an exact comparison, not LIKE ──
$con1 = new FakeMysqli([], 0);
LogData::list($con1, 1, 20, ['userId' => 7]);
check(count($con1->prepared) === 2, '1: LogData::list prepares two statements (rows + count)');
$sql0 = $con1->prepared[0]->sql;
assertContains('l.idUser = ?', $sql0, "1: userId filter compiles to an exact 'l.idUser = ?' comparison");
assertNotContains('LIKE', $sql0, '1: a userId-only filter set produces no LIKE clause at all');
check($con1->prepared[0]->types === 'iii', "1: main query bind types are 'iii' (idUser + LIMIT + OFFSET), got: " . $con1->prepared[0]->types);
check($con1->prepared[0]->values[0] === 7, '1: bound idUser value is the int 7 (exact, not a %7% LIKE pattern)');
check($con1->prepared[1]->types === 'i', "1: count query bind types are 'i', got: " . $con1->prepared[1]->types);
check($con1->prepared[1]->values[0] === 7, '1: count query also binds the exact idUser value');

// ── 2. LogData: existing 'user' filter is untouched (still LIKE) ─────────
$con2 = new FakeMysqli([], 0);
LogData::list($con2, 1, 20, ['user' => 'erik']);
assertContains('LIKE', $con2->prepared[0]->sql, "2: pre-existing 'user' filter is still a LIKE (regression guard, not touched by this change)");

// ── 3. Activity::render renders no filter controls ───────────────────────
$rows3 = [
    ['id' => 1, 'logTime' => '2026-07-20 10:00:00', 'origin' => 'zeit', 'context' => 'login',
     'activity' => 'Login erfolgreich', 'ip' => '1.2.3.4', 'username' => 'erika'],
];
$con3 = new FakeMysqli($rows3, 1);
ob_start();
Activity::render(['con' => $con3, 'userId' => 7, 'page' => 1]);
$html3 = (string) ob_get_clean();

assertNotContains('logFilterForm', $html3, '3: no filter form (id="logFilterForm") in Activity output');
assertNotContains('log_app', $html3, '3: no App filter select');
assertNotContains('log_context', $html3, '3: no Kontext filter select');
assertNotContains('log_user', $html3, '3: no Benutzer filter input');
assertNotContains('log_from', $html3, '3: no Von-date filter input');
assertNotContains('log_to', $html3, '3: no Bis-date filter input');
assertNotContains('Filter anwenden', $html3, '3: no "Filter anwenden" button');
assertNotContains('Zurücksetzen', $html3, '3: no filter reset button');

assertContains('<th>Zeit</th>', $html3, '3: Zeit column header present');
assertContains('<th>App</th>', $html3, '3: App column header present');
assertContains('<th>Kontext</th>', $html3, '3: Kontext column header present');
assertContains('<th>Aktivität</th>', $html3, '3: Aktivität column header present');
assertContains('Login erfolgreich', $html3, '3: seeded row activity text is rendered');
check($con3->prepared[0]->values[0] === 7, '3: Activity called LogData with userId=7 from cfg');

// ── 4. GET parameters cannot override the userId filter ──────────────────
$_GET['userId'] = '999';
$_GET['user']   = 'someone-else';
$con4 = new FakeMysqli([], 0);
ob_start();
Activity::render(['con' => $con4, 'userId' => 7, 'page' => 1]);
ob_end_clean();
check($con4->prepared[0]->values[0] === 7, "4: userId filter stays 7 even though \$_GET['userId']=999 was set (GET cannot override the fixed filter)");
assertNotContains('LIKE', $con4->prepared[0]->sql, "4: \$_GET['user'] does not sneak in a LIKE filter either — only userId is ever passed");
unset($_GET['userId'], $_GET['user']);

// ── 5. page falls back to $_GET['page'] when not passed via cfg ──────────
$_GET['page'] = '3';
$con5 = new FakeMysqli([], 100); // total=100, perPage default 20 => 5 pages
ob_start();
Activity::render(['con' => $con5, 'userId' => 7]); // no 'page' key in cfg
$html5 = (string) ob_get_clean();
unset($_GET['page']);
assertContains('<a class="page-link active" href="?page=3">3</a>', $html5, '5: page falls back to $_GET[\'page\'] (=3) and marks it active');
check(substr_count($html5, 'class="page-link') === 5, '5: five pagination links rendered for total=100/perPage=20');

// ── 6. explicit cfg 'page' wins over $_GET['page'] ────────────────────────
$_GET['page'] = '4';
$con6 = new FakeMysqli([], 100);
ob_start();
Activity::render(['con' => $con6, 'userId' => 7, 'page' => 2]);
$html6 = (string) ob_get_clean();
unset($_GET['page']);
assertContains('<a class="page-link active" href="?page=2">2</a>', $html6, "6: explicit cfg['page']=2 wins over \$_GET['page']=4");

// ── Summary ────────────────────────────────────────────────────────────
echo "\n";
if ($failures !== []) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
}
echo "{$passed}/{$total} ok\n";
exit($passed === $total ? 0 : 1);
