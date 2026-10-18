<?php

namespace Tests\Feature;

use App\Services\JgAgente\AgentClientService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class HmacAgentClientConfigAndSqliteTest extends TestCase
{
    public function test_agent_client_resolves_secret_from_config_and_includes_key_id_and_correlation_id(): void
    {
        Config::set('services.jg_agente.secret', 'test-secret-123456');
        Config::set('services.jg_agente.key_id', 'jg_filmmaker_v2');
        Config::set('services.jg_agente.mock', false);

        $client = new AgentClientService;
        $headers = $client->generateSignatureHeaders('POST', '/api/v1/tools/test', '{"foo":"bar"}');

        $this->assertNotEmpty($headers['X-Signature']);
        $this->assertNotEmpty($headers['X-Timestamp']);
        $this->assertNotEmpty($headers['X-Nonce']);
        $this->assertSame('jg_filmmaker_v2', $headers['X-Key-ID']);
        $this->assertSame('jg_filmmaker', $headers['X-Agent-ID']);
        $this->assertNotEmpty($headers['X-Correlation-ID']);
    }

    public function test_llm_config_does_not_mix_keys_across_providers(): void
    {
        Config::set('services.llm.provider', 'groq');
        Config::set('services.llm.model', 'openai/gpt-oss-120b');

        $this->assertSame('groq', config('services.llm.provider'));
        $this->assertSame('openai/gpt-oss-120b', config('services.llm.model'));
    }

    public function test_sqlite_configuration_has_wal_and_busy_timeout(): void
    {
        $sqliteConfig = config('database.connections.sqlite');

        $this->assertSame(5000, $sqliteConfig['busy_timeout']);
        $this->assertSame('WAL', $sqliteConfig['journal_mode']);
        $this->assertSame('NORMAL', $sqliteConfig['synchronous']);
        $this->assertSame('IMMEDIATE', $sqliteConfig['transaction_mode']);
    }

    public function test_agent_client_fails_fast_on_non_serializable_payload(): void
    {
        Config::set('services.jg_agente.secret', 'test-secret');
        Config::set('services.jg_agente.mock', false);

        $client = new AgentClientService;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload no serializable');

        // Contiene un valor inválido UTF-8 que rompe json_encode
        $invalidUtf8Payload = [
            'raw_text' => "\xB1\x31",
        ];

        $client->callTool('treatment_structuring', $invalidUtf8Payload);
    }
}
