<?php
require __DIR__ . '/../vendor/autoload.php';

use Pusher\Pusher;

$options = ['cluster' => 'ap3', 'useTLS' => true];
$pusher = new Pusher(
    "0ebdc1f9053f6e8f9965",
    "8f8f9545a60efc26b30e",
    "2123863",
    $options
);

$result = $pusher->trigger('test-channel', 'test-event', ['message' => 'Hello from PHP!']);

if($result) {
    echo "Success! Check your Pusher Debug Console.";
} else {
    echo "Failed to send.";
}