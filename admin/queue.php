<?php
include 'connect.php';

// Delete queue items older than 1 minute (60 seconds)
$cleanup_query = "DELETE FROM queue WHERE created_at < NOW() - INTERVAL 60 SECOND";
$con->query($cleanup_query);

// Fetch all current queue items with walk-in details
$queue_query = "SELECT q.*, w.appointment_number, w.name
                FROM queue q
                JOIN walk_in w ON q.walk_in_id = w.id
                ORDER BY q.created_at ASC";
$queue_result = $con->query($queue_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Queue Display</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --text-color: #333;
            --background-color: #f8f9fa;
            --queue-item-bg: #ffffff;
        }
        
        body {
            background-color: var(--background-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
        }

        .queue-container {
            width: 100%;
            height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            margin: 0;
            font-size: 2.5rem;
        }

        .current-time {
            font-size: 1.5rem;
            margin-top: 10px;
        }

        .queue-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .queue-item {
            background-color: var(--queue-item-bg);
            border: 3px solid var(--primary-color);
            border-radius: 12px;
            padding: 30px 50px;
            text-align: center;
            min-width: 300px;
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .queue-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.2);
        }

        .appointment-number {
            font-weight: bold;
            font-size: 4rem;
            margin-bottom: 15px;
            color: var(--secondary-color);
        }

        .customer-name {
            font-size: 2.5rem;
        }

        .no-queue {
            font-size: 2.5rem;
            text-align: center;
            margin-top: 50px;
            color: #7f8c8d;
        }

        .footer {
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 1rem;
        }

        .fullscreen-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .fullscreen-btn:hover {
            background-color: var(--secondary-color);
        }

        @media (max-width: 768px) {
            .queue-item {
                min-width: 250px;
                padding: 20px 30px;
            }
            
            .appointment-number {
                font-size: 3rem;
            }
            
            .customer-name {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

<div class="queue-container">
    <div class="header">
        <h1>Now Serving</h1>
        <div class="current-time" id="current-time"></div>
    </div>

    <div class="queue-wrapper" id="queue-wrapper">
        <?php if ($queue_result->num_rows > 0): ?>
            <?php while ($row = $queue_result->fetch_assoc()): ?>
                <div class="queue-item">
                    <div class="appointment-number"><?= htmlspecialchars($row['appointment_number']) ?></div>
                    <div class="customer-name"><?= htmlspecialchars($row['name']) ?></div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-queue"></div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <button class="fullscreen-btn" id="fullscreen-btn">
            Toggle Fullscreen
        </button>
    </div>
</div>

<script>
    // Update current time
    function updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString();
        document.getElementById('current-time').textContent = timeString;
    }
    
    // Update time immediately and then every second
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);

    // Auto-refresh the queue every 5 seconds
    function refreshQueue() {
        fetch('queue.php')
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const newQueueContent = doc.getElementById('queue-wrapper').innerHTML;
                document.getElementById('queue-wrapper').innerHTML = newQueueContent;
            });
    }

    setInterval(refreshQueue, 5000); // 5000 milliseconds = 5 seconds

    // Fullscreen functionality
    document.getElementById('fullscreen-btn').addEventListener('click', function() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.error(`Error attempting to enable fullscreen: ${err.message}`);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    });

    // Also allow F11 to toggle fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F11') {
            e.preventDefault();
            document.getElementById('fullscreen-btn').click();
        }
    });
</script>

</body>
</html>