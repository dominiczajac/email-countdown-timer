<?php
/** Isolated privacy-guide contract. Real wp-admin coverage lives in browser CI. */
if (PHP_SAPI !== 'cli') exit(1);
define('ABSPATH', __DIR__.'/');
$GLOBALS['policy_calls'] = [];
$GLOBALS['policy_admin'] = false;
$GLOBALS['policy_bad_translation'] = false;
function is_admin() { return $GLOBALS['policy_admin']; }
function esc_html__($text, $domain) {
    if ($domain !== 'easy-countdown') throw new RuntimeException('Wrong text domain');
    return htmlspecialchars($GLOBALS['policy_bad_translation'] ? '<script>unsafe</script>' : $text, ENT_QUOTES, 'UTF-8');
}
if (($argv[1] ?? '') !== 'missing') {
    function wp_add_privacy_policy_content($name, $content) { $GLOBALS['policy_calls'][] = [$name, $content]; }
}
require dirname(__DIR__).'/includes/class-email-countdown-timer-privacy.php';
Email_Countdown_Timer_Privacy::suggest();
if ($GLOBALS['policy_calls'] !== []) throw new RuntimeException('Policy emitted outside admin');
$GLOBALS['policy_admin'] = true;
Email_Countdown_Timer_Privacy::suggest();
if (($argv[1] ?? '') === 'missing') {
    if ($GLOBALS['policy_calls'] !== []) throw new RuntimeException('Missing API guard failed');
    echo "PASS privacy guide: missing-API guard\n";
    exit;
}
$policy = $GLOBALS['policy_calls'][0] ?? null;
if (!$policy || $policy[0] !== 'Easy Countdown' || !str_contains($policy[1], 'privacy-policy-tutorial') || !str_contains($policy[1], 'HTTP request')) throw new RuntimeException('Incomplete disclosure');
if (str_contains($policy[1], 'GDPR compliant')) throw new RuntimeException('Unjustified compliance claim');
$GLOBALS['policy_bad_translation'] = true;
Email_Countdown_Timer_Privacy::suggest();
if (str_contains($GLOBALS['policy_calls'][1][1], '<script>')) throw new RuntimeException('Translation escaped incorrectly');
echo "PASS privacy guide: admin-only suggestion, infrastructure limits and escaped translation\n";
