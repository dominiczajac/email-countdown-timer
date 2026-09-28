<?php
/** Only the isolated suite uses this double; real session tests run separately. */
if (PHP_SAPI !== 'cli' || !function_exists('ok')) { exit(1); }
define('DB_NAME', 'ect_test');
define('ARRAY_A', 'ARRAY_A');
final class Email_Countdown_Timer_Lock_DB_Double {
    public string $options = 'test_options';
    public int $connection = 77;
    public bool $allow = true;
    public string $failure = '';
    public string $last_error = '';
    public bool $suppressed = false;
    public function suppress_errors($value = true) { $old = $this->suppressed; $this->suppressed = (bool)$value; return $old; }
    public ?int $owner = null;
    public bool $lose_on_check = false;
    public array $names = [];
    public function prepare($sql, ...$args) { return json_encode([$sql, $args]); }
    public function get_row($prepared, $format) {
        [$sql, $args] = json_decode($prepared, true);
        $this->names[] = $args[0];
        $this->last_error = '';
        if ($this->failure === 'exception') throw new RuntimeException('Simulated database error');
        if ($this->failure === 'query') { $this->last_error = 'Test database unavailable'; return null; }
        if ($this->failure === 'null') return ['acquired'=>null,'connection_id'=>$this->connection];
        if ($this->failure === 'malformed') return ['unexpected'=>true];
        if (!$this->allow) return ['acquired'=>'0', 'connection_id'=>$this->connection];
        $this->owner=$this->connection;
        return ['acquired'=>'1','connection_id'=>$this->connection];
    }
    public function get_var($prepared) {
        [$sql, $args] = json_decode($prepared, true);
        if ($this->lose_on_check && str_contains($sql,'IS_USED_LOCK')) ++$this->connection;
        $owns=$this->owner===$this->connection && $args[0]===$this->connection;
        if (str_contains($sql,'RELEASE_LOCK') && $owns) $this->owner=null;
        return $owns ? '1' : '0';
    }
}
$GLOBALS['wpdb'] = new Email_Countdown_Timer_Lock_DB_Double();
function wp_using_ext_object_cache() { return !empty($GLOBALS['ect_ext_cache']); }
function wp_cache_delete($key, $group) { return true; }
function wp_cache_get($key, $group, $force=false) { $GLOBALS['ect_force_read']=$force; return $GLOBALS['transients'][$key] ?? false; }
