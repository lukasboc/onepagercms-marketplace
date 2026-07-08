<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemVersion;
use App\Models\User;
use App\Models\VersionCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class VersionCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $developer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        $this->developer = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeZip(array $manifest, array $files): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'opcms-check-test-').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $manifestName = ($manifest['type'] ?? 'plugin') === 'theme' ? 'theme.json' : 'plugin.json';
        $zip->addFromString($manifestName, json_encode($manifest));
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return new UploadedFile($path, "{$manifest['slug']}.zip", 'application/zip', null, true);
    }

    private function pluginManifest(string $slug, array $overrides = []): array
    {
        return array_merge([
            'slug' => $slug,
            'type' => 'plugin',
            'name' => 'Test Plugin',
            'version' => '1.0.0',
            'main' => 'main.php',
            'description' => 'Does test things.',
            'author' => 'Tester',
            'requires_opcms' => '1.2.0',
        ], $overrides);
    }

    private function submit(array $manifest, array $files): ItemVersion
    {
        $this->actingAs($this->developer)
            ->post(route('developer.items.store'), [
                'zip' => $this->makeZip($manifest, $files),
                'summary' => 'A test submission.',
                'is_paid' => '0',
            ])
            ->assertSessionHasNoErrors();

        return Item::where('slug', $manifest['slug'])->firstOrFail()->versions()->firstOrFail();
    }

    private function runCheck(ItemVersion $version, string $check): VersionCheck
    {
        $this->actingAs($this->admin)
            ->post(route('admin.review.checks.run', [$version, $check]))
            ->assertRedirect(route('admin.review.show', $version));

        return $version->checks()->where('check', $check)->firstOrFail();
    }

    private function assertHasFindingContaining(VersionCheck $result, string $needle): void
    {
        $this->assertTrue(
            collect($result->findings)->contains(fn ($finding) => str_contains($finding, $needle)),
            "No finding contains \"{$needle}\". Findings: ".json_encode($result->findings),
        );
    }

    public function test_checks_are_admin_only_and_unknown_checks_are_rejected(): void
    {
        // Built via factories: submit() would leave the developer authenticated,
        // which breaks the guest assertion below.
        $item = Item::factory()->pending()->create(['user_id' => $this->developer->id]);
        $version = ItemVersion::factory()->for($item)->create();

        $this->post(route('admin.review.checks.run', [$version, 'manifest']))
            ->assertRedirect(route('login'));

        $this->actingAs($this->developer)
            ->post(route('admin.review.checks.run', [$version, 'manifest']))
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->post("/admin/review/{$version->id}/checks/nonsense")
            ->assertNotFound();

        $this->assertSame(0, VersionCheck::count());
    }

    public function test_manifest_check_passes_for_complete_manifest(): void
    {
        $version = $this->submit($this->pluginManifest('complete-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_MANIFEST);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertSame($this->admin->id, $result->runner_id);
        $this->assertHasFindingContaining($result, 'complete-plugin');
    }

    public function test_manifest_check_warns_about_missing_recommended_fields(): void
    {
        $manifest = $this->pluginManifest('sparse-plugin');
        unset($manifest['description'], $manifest['author'], $manifest['requires_opcms']);
        $version = $this->submit($manifest, [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_MANIFEST);

        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, 'description');
    }

    public function test_hooks_check_passes_for_known_cms_hooks(): void
    {
        $version = $this->submit($this->pluginManifest('good-hooks'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\nadd_filter('opcms_custom_css', fn (\$css) => \$css);\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_HOOKS);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
    }

    public function test_hooks_check_fails_for_unknown_hook(): void
    {
        $version = $this->submit($this->pluginManifest('bad-hooks'), [
            'main.php' => "<?php\nadd_action('opcms_totally_fake', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_HOOKS);

        $this->assertSame(VersionCheck::STATUS_FAILED, $result->status);
        $this->assertHasFindingContaining($result, 'opcms_totally_fake');
        $this->assertHasFindingContaining($result, 'main.php:2');
    }

    public function test_hooks_check_warns_about_dynamic_hook_names(): void
    {
        $version = $this->submit($this->pluginManifest('dynamic-hooks'), [
            'main.php' => "<?php\n\$hook = 'opcms_body_end';\nadd_action(\$hook, function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_HOOKS);

        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, 'dynamic hook name');
    }

    public function test_hooks_check_allows_custom_hooks_fired_by_the_plugin(): void
    {
        $version = $this->submit($this->pluginManifest('custom-hooks'), [
            'main.php' => "<?php\nadd_action('customhooks_loaded', function () {});\nadd_action('opcms_body_end', function () { do_action('customhooks_loaded'); });\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_HOOKS);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, 'customhooks_loaded');
    }

    public function test_hooks_check_fails_for_foreign_lifecycle_slug(): void
    {
        $version = $this->submit($this->pluginManifest('own-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_uninstall_other-plugin', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_HOOKS);

        $this->assertSame(VersionCheck::STATUS_FAILED, $result->status);
        $this->assertHasFindingContaining($result, 'foreign slug');
    }

    public function test_uninstall_check_fails_when_tables_are_created_without_uninstall_hook(): void
    {
        $version = $this->submit($this->pluginManifest('leaky-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_activate_leaky-plugin', function () {\n    \$db->exec('CREATE TABLE IF NOT EXISTS leaky (id INTEGER)');\n});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_UNINSTALL);

        $this->assertSame(VersionCheck::STATUS_FAILED, $result->status);
        $this->assertHasFindingContaining($result, 'opcms_uninstall_leaky-plugin');
    }

    public function test_uninstall_check_passes_when_cleanup_is_present(): void
    {
        $version = $this->submit($this->pluginManifest('tidy-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_activate_tidy-plugin', function () {\n    \$db->exec('CREATE TABLE IF NOT EXISTS tidy (id INTEGER)');\n});\nadd_action('opcms_uninstall_tidy-plugin', function () {\n    \$db->exec('DROP TABLE IF EXISTS tidy');\n});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_UNINSTALL);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
    }

    public function test_uninstall_check_passes_when_no_cleanup_is_needed(): void
    {
        $version = $this->submit($this->pluginManifest('stateless-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () { echo '<!-- hi -->'; });\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_UNINSTALL);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, 'not required');
    }

    public function test_malware_check_flags_eval_with_base64(): void
    {
        $version = $this->submit($this->pluginManifest('shady-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\neval(base64_decode('cGhwaW5mbygpOw=='));\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_MALWARE);

        $this->assertSame(VersionCheck::STATUS_FAILED, $result->status);
        $this->assertHasFindingContaining($result, 'eval()');
        $this->assertHasFindingContaining($result, 'main.php:3');
    }

    public function test_malware_check_warns_about_obfuscation_helpers(): void
    {
        $version = $this->submit($this->pluginManifest('rot-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () { echo str_rot13('uryyb'); });\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_MALWARE);

        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, 'obfuscation');
    }

    public function test_malware_check_passes_for_clean_plugin(): void
    {
        $version = $this->submit($this->pluginManifest('clean-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () { echo '<!-- clean -->'; });\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_MALWARE);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
    }

    public function test_functionality_check_passes_for_working_plugin(): void
    {
        $version = $this->submit($this->pluginManifest('working-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () { echo '<!-- ok -->'; });\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_FUNCTIONALITY);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, '1 action(s)');
    }

    public function test_functionality_check_fails_for_fatal_error(): void
    {
        $version = $this->submit($this->pluginManifest('crashing-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\nthis_function_does_not_exist();\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_FUNCTIONALITY);

        $this->assertSame(VersionCheck::STATUS_FAILED, $result->status);
        $this->assertHasFindingContaining($result, 'this_function_does_not_exist');
    }

    public function test_functionality_check_reports_registered_section_types(): void
    {
        $version = $this->submit($this->pluginManifest('section-plugin'), [
            'main.php' => "<?php\nopcms_register_section_type('demo', [\n    'label' => 'Demo',\n    'build' => function (\$row) { return null; },\n    'form_url' => 'extensions/section-plugin/form.php',\n]);\nadd_action('opcms_uninstall_section-plugin', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_FUNCTIONALITY);

        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, 'demo');
    }

    public function test_rerunning_a_check_updates_the_existing_result(): void
    {
        $version = $this->submit($this->pluginManifest('rerun-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\n",
        ]);

        $first = $this->runCheck($version, VersionCheck::CHECK_MALWARE);
        $second = $this->runCheck($version, VersionCheck::CHECK_MALWARE);

        $this->assertSame(1, VersionCheck::count());
        $this->assertSame($first->id, $second->id);
    }

    public function test_plugin_only_checks_are_skipped_for_themes(): void
    {
        $version = $this->submit([
            'slug' => 'test-theme',
            'type' => 'theme',
            'name' => 'Test Theme',
            'version' => '1.0.0',
            'description' => 'A theme.',
            'author' => 'Tester',
            'requires_opcms' => '1.2.0',
        ], [
            'templates/index.php' => "<?php\necho 'theme';\n",
            'style.css' => 'body { color: black; }',
        ]);

        foreach ([VersionCheck::CHECK_HOOKS, VersionCheck::CHECK_UNINSTALL, VersionCheck::CHECK_FUNCTIONALITY] as $check) {
            $this->assertSame(VersionCheck::STATUS_SKIPPED, $this->runCheck($version, $check)->status);
        }

        $this->assertSame(VersionCheck::STATUS_PASSED, $this->runCheck($version, VersionCheck::CHECK_MANIFEST)->status);
        $this->assertSame(VersionCheck::STATUS_PASSED, $this->runCheck($version, VersionCheck::CHECK_MALWARE)->status);
    }

    public function test_review_page_shows_check_results(): void
    {
        $version = $this->submit($this->pluginManifest('ui-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\n",
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.review.show', $version))
            ->assertOk()
            ->assertSee('Automated checks')
            ->assertSee('Malware scan')
            ->assertSee('Run check');

        $this->runCheck($version, VersionCheck::CHECK_MALWARE);

        $this->actingAs($this->admin)
            ->get(route('admin.review.show', $version))
            ->assertOk()
            ->assertSee('Passed')
            ->assertSee('Re-run')
            ->assertSee('No suspicious code patterns found.');
    }

    private function themeManifest(string $slug, array $overrides = []): array
    {
        return array_merge([
            'slug' => $slug,
            'type' => 'theme',
            'name' => 'Test Theme',
            'version' => '1.0.0',
            'description' => 'A theme.',
            'author' => 'Tester',
            'requires_opcms' => '1.2.0',
        ], $overrides);
    }

    public function test_theme_options_check_passes_when_all_options_are_used(): void
    {
        $version = $this->submit($this->themeManifest('used-theme', [
            'options' => [
                ['key' => 'sidebar-bg', 'type' => 'color', 'default' => '#1e2126'],
                ['key' => 'font-display', 'type' => 'select', 'default' => 'Sora', 'choices' => ['Sora', 'System']],
            ],
        ]), [
            'templates/styles.php' => "<?php\n\$bg = \$settingactions->getSettingValue('theme-option:used-theme:sidebar-bg');\n",
            'templates/head.php' => "<?php\n\$font = opcms_theme_option('font-display', 'Sora');\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, 'All 2 declared option(s) are referenced');
    }

    public function test_theme_options_check_warns_about_unused_options(): void
    {
        $version = $this->submit($this->themeManifest('unused-theme', [
            'options' => [
                ['key' => 'sidebar-bg', 'type' => 'color', 'default' => '#1e2126'],
                ['key' => 'never-used', 'type' => 'color', 'default' => '#ffffff'],
            ],
        ]), [
            'templates/styles.php' => "<?php\n\$bg = opcms_theme_option('sidebar-bg');\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, '"never-used" is never referenced');
    }

    public function test_theme_options_check_warns_about_undeclared_option_reads(): void
    {
        $version = $this->submit($this->themeManifest('typo-theme', [
            'options' => [
                ['key' => 'sidebar-bg', 'type' => 'color', 'default' => '#1e2126'],
            ],
        ]), [
            'templates/styles.php' => "<?php\n\$a = opcms_theme_option('sidebar-bg');\n\$b = \$settingactions->getSettingValue('theme-option:typo-theme:sidebarbg');\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, '"sidebarbg" is read in templates/styles.php but not declared');
    }

    public function test_theme_options_check_warns_about_invalid_option_entries(): void
    {
        $version = $this->submit($this->themeManifest('invalid-theme', [
            'options' => [
                ['key' => 'font-display', 'type' => 'select', 'default' => 'Sora'],
            ],
        ]), [
            'templates/head.php' => "<?php\n\$font = opcms_theme_option('font-display');\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_WARNING, $result->status);
        $this->assertHasFindingContaining($result, 'Select option "font-display" has no valid "choices"');
    }

    public function test_theme_options_check_is_skipped_for_plugins(): void
    {
        $version = $this->submit($this->pluginManifest('options-plugin'), [
            'main.php' => "<?php\nadd_action('opcms_body_end', function () {});\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_SKIPPED, $result->status);
        $this->assertHasFindingContaining($result, 'only applies to themes');
    }

    public function test_theme_options_check_passes_for_theme_without_options(): void
    {
        $version = $this->submit($this->themeManifest('plain-theme'), [
            'templates/index.php' => "<?php\necho 'theme';\n",
        ]);

        $result = $this->runCheck($version, VersionCheck::CHECK_THEME_OPTIONS);
        $this->assertSame(VersionCheck::STATUS_PASSED, $result->status);
        $this->assertHasFindingContaining($result, 'declares no options');
    }
}
