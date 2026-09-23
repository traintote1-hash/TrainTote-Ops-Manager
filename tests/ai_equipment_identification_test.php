<?php
require_once dirname(__DIR__) . '/ai/equipment_identifier.php';

function aiEquipmentExpect($condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$visual = [
    'reporting_marks' => 'CP',
    'road_number' => '1504',
    'road_name' => 'Canadian Pacific',
    'equipment_class' => 'Locomotive',
    'equipment_type' => 'Diesel',
    'prototype_design' => 'EMD GP9',
    'manufacturer' => '',
    'service' => 'Road Switcher',
    'color' => 'Red',
    'length_ft' => '54',
    'scale' => 'HO',
    'load_status' => 'Empty',
    'notes' => '',
    'reporting_marks_confidence' => 'high',
    'road_number_confidence' => 'high',
    'ai_confidence' => 'medium',
    'ai_review_notes' => '',
];

aiEquipmentExpect(ttAiShouldWebVerify($visual), 'A railroad identity plus road number must trigger web verification.');
aiEquipmentExpect(!ttAiShouldWebVerify(array_merge($visual, ['road_number' => ''])), 'A blank road number must not trigger a speculative web search.');
aiEquipmentExpect(ttAiShouldWebVerify(array_merge($visual, ['road_name' => ''])), 'Reporting marks plus road number must be sufficient for web verification.');

$prompt = ttAiWebVerificationPrompt($visual);
aiEquipmentExpect(strpos($prompt, '"Canadian Pacific 1504" "model train"') !== false, 'Verification must start with the exact railroad, road number, and model train query.');
aiEquipmentExpect(strpos($prompt, '"CP 1504" "model train"') !== false, 'Verification must also search the exact reporting marks and road number.');
aiEquipmentExpect(strpos($prompt, 'GP9, GP15, GP15-1') !== false, 'Verification must explicitly distinguish visually similar GP locomotive models.');
aiEquipmentExpect(strpos($prompt, 'Preserve reporting_marks and road_number exactly') !== false, 'Web results must not overwrite image-grounded identity fields.');
aiEquipmentExpect(strpos($prompt, 'prototype builder') !== false && strpos($prompt, 'model manufacturer') !== false, 'Verification must distinguish prototype builders from model manufacturers.');

$payload = ttAiWebVerificationPayload($visual, 'image/jpeg', 'abc123');
aiEquipmentExpect(($payload['tools'][0]['type'] ?? '') === 'web_search', 'The verification request must enable Responses API web search.');
aiEquipmentExpect(($payload['tool_choice'] ?? '') === 'required', 'The verification request must require a web search rather than leaving it optional.');
aiEquipmentExpect(($payload['tools'][0]['search_context_size'] ?? '') === 'medium', 'The web search must use enough context to compare exact product listings.');
aiEquipmentExpect(($payload['input'][0]['content'][1]['detail'] ?? '') === 'high', 'Both identification passes must inspect the source photo at high detail.');
aiEquipmentExpect(($payload['text']['format']['type'] ?? '') === 'json_schema', 'The web-verified identification must use structured JSON output.');

$fakeResponse = [
    'output' => [
        [
            'type' => 'web_search_call',
            'action' => [
                'sources' => [[
                    'url' => 'https://example.com/cp-1504',
                    'title' => 'Walthers CP 1504',
                ]],
            ],
        ],
        [
            'type' => 'message',
            'content' => [[
                'type' => 'output_text',
                'text' => json_encode(array_merge($visual, [
                    'prototype_design' => 'EMD GP15-1',
                    'manufacturer' => 'Walthers',
                    'ai_confidence' => 'high',
                ])),
                'annotations' => [[
                    'type' => 'url_citation',
                    'url' => 'https://example.com/cp-1504',
                    'title' => 'Walthers CP 1504',
                ]],
            ]],
        ],
    ],
];
$decoded = ttAiDecodeEquipmentResponse($fakeResponse);
$sources = ttAiResponseSources($fakeResponse);
aiEquipmentExpect($decoded['prototype_design'] === 'EMD GP15-1' && $decoded['manufacturer'] === 'Walthers', 'The parser must read the message after a preceding web-search output item.');
aiEquipmentExpect(count($sources) === 1 && $sources[0]['url'] === 'https://example.com/cp-1504', 'Verification sources must be deduplicated and retained for review.');

$fallback = ttAiAppendReviewNote(array_merge($visual, ['ai_confidence' => 'high']), 'Verification unavailable.');
aiEquipmentExpect($fallback['ai_confidence'] === 'medium' && strpos($fallback['ai_review_notes'], 'Verification unavailable.') !== false, 'A failed web verification must lower confidence and request manual review.');

$analyzer = file_get_contents(dirname(__DIR__) . '/ai/analyze_equipment.php');
$add = file_get_contents(dirname(__DIR__) . '/equipment/add.php');
aiEquipmentExpect(strpos($analyzer, 'ttAiWebVerificationPayload') !== false && strpos($analyzer, "'web_verified' => \$webVerified") !== false, 'The scanner must run and retain the web verification result.');
aiEquipmentExpect(strpos($add, 'Web verification completed.') !== false && strpos($add, 'verification_sources') !== false, 'Add Equipment must show that web verification ran and expose its sources.');

echo "ai_equipment_identification_test: OK\n";
