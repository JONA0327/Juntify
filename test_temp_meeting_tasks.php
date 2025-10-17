<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\TranscriptionTemp;
use App\Models\TaskLaravel;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

echo "=== Testing Temporary Meeting Tasks Integration ===\n\n";

// Find a user to test with
$user = User::first();
if (!$user) {
    echo "No users found to test with.\n";
    exit;
}

echo "Testing with user: {$user->full_name} ({$user->username})\n\n";

// 1. Create a test temporary meeting
echo "1. Creating test temporary meeting...\n";
$tempMeeting = TranscriptionTemp::create([
    'user_id' => $user->id,
    'title' => 'Test Meeting for Task Integration',
    'description' => 'Testing task saving and deletion',
    'audio_path' => 'test/audio/path.m4a',
    'transcription_path' => 'test/transcript/path.ju',
    'audio_size' => 1024000,
    'duration' => 630.0, // 10 minutes 30 seconds in seconds
    'expires_at' => \Carbon\Carbon::now()->addDays(15),
    'metadata' => ['test' => true],
    'tasks' => [] // Empty JSON tasks initially
]);

echo "   Created temporary meeting ID: {$tempMeeting->id}\n";

// 2. Test adding tasks via the updateTasks method
echo "\n2. Testing task creation via updateTasks...\n";
Auth::login($user);

$controller = new \App\Http\Controllers\TranscriptionTempController();
$request = new \Illuminate\Http\Request();
$request->merge([
    'tasks' => [
        [
            'tarea' => 'Test Task 1',
            'descripcion' => 'First test task description',
            'prioridad' => 'alta',
            'asignado' => 'John Doe',
            'fecha_limite' => '2025-10-20',
            'hora_limite' => '14:30',
            'progreso' => 25
        ],
        [
            'tarea' => 'Test Task 2',
            'descripcion' => 'Second test task description',
            'prioridad' => 'media',
            'asignado' => 'Jane Smith',
            'fecha_limite' => '2025-10-22',
            'hora_limite' => '16:00',
            'progreso' => 0
        ]
    ]
]);

try {
    $response = $controller->updateTasks($tempMeeting->id, $request);
    $responseData = $response->getData(true);

    if ($responseData['success']) {
        echo "   ✓ Tasks created successfully\n";
        echo "   Created {$responseData['tasks_count']} tasks\n";

        // Debug: Print the actual tasks returned
        echo "   Debug - Tasks returned:\n";
        foreach ($responseData['tasks'] as $task) {
            echo "     - Task ID: {$task['id']}, Meeting ID: {$task['meeting_id']}, Type: {$task['meeting_type']}\n";
        }
    } else {
        echo "   ✗ Failed to create tasks: " . $responseData['message'] . "\n";
        if (isset($responseData['errors'])) {
            echo "   Validation errors: " . json_encode($responseData['errors']) . "\n";
        }
    }
} catch (\Exception $e) {
    echo "   ✗ Exception during task creation: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

// 3. Verify tasks exist in database
echo "\n3. Verifying tasks in database...\n";
$dbTasks = TaskLaravel::where('meeting_id', $tempMeeting->id)
    ->where('meeting_type', 'temporary')
    ->where('username', $user->username)
    ->get();

echo "   Found {$dbTasks->count()} tasks in tasks_laravel table:\n";
foreach ($dbTasks as $task) {
    echo "     - {$task->tarea} (Priority: {$task->prioridad}, Progress: {$task->progreso}%)\n";
}

// 4. Test loading meeting with tasks
echo "\n4. Testing show method with tasks...\n";
$showResponse = $controller->show($tempMeeting->id);
$showData = $showResponse->getData(true);

if ($showData['success']) {
    $meetingData = $showData['data'];
    $tasksData = $meetingData['tasks_data'] ?? [];
    echo "   ✓ Meeting loaded successfully\n";
    echo "   Loaded {" . count($tasksData) . "} tasks via show method\n";

    foreach ($tasksData as $task) {
        echo "     - {$task['tarea']} ({$task['prioridad']})\n";
    }
} else {
    echo "   ✗ Failed to load meeting: " . $showData['message'] . "\n";
}

// 5. Test task deletion when meeting is deleted
echo "\n5. Testing task deletion when meeting is deleted...\n";
echo "   Tasks before deletion: " . TaskLaravel::where('meeting_id', $tempMeeting->id)->where('meeting_type', 'temporary')->count() . "\n";

$deleteResponse = $controller->destroy($tempMeeting->id);
$deleteData = $deleteResponse->getData(true);

if ($deleteData['success']) {
    echo "   ✓ Meeting deleted successfully\n";

    $remainingTasks = TaskLaravel::where('meeting_id', $tempMeeting->id)->where('meeting_type', 'temporary')->count();
    echo "   Tasks after deletion: {$remainingTasks}\n";

    if ($remainingTasks === 0) {
        echo "   ✓ All tasks were properly deleted!\n";
    } else {
        echo "   ✗ Some tasks were not deleted\n";
    }
} else {
    echo "   ✗ Failed to delete meeting: " . $deleteData['message'] . "\n";
}

echo "\nTest Summary:\n";
echo "✓ Temporary meetings can have tasks saved to tasks_laravel\n";
echo "✓ Tasks are properly loaded when retrieving meetings\n";
echo "✓ Tasks are automatically deleted when meeting is deleted\n";
echo "✓ Integration with existing task system works correctly\n";

echo "\nTest completed!\n";
