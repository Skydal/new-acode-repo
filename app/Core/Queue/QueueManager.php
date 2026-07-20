@@
-        $sql = "SELECT id FROM {$this->table}
-                WHERE status = 'pending'
-                AND (locked_at IS NULL OR locked_at < DATE_SUB(%s, INTERVAL 10 MINUTE))
-                ORDER BY created_at ASC
-                LIMIT %d";
-
-        $now = current_time('mysql');
-        $jobIds = $this->db->get_col($this->db->prepare($sql, $now, $limit));
+        // Select jobs that are pending and whose locked_at has passed (or never locked)
+        // This allows us to set locked_at in the future to implement backoff retries.
+        $sql = "SELECT id FROM {$this->table}
+                WHERE status = 'pending'
+                AND (locked_at IS NULL OR locked_at <= %s)
+                ORDER BY created_at ASC
+                LIMIT %d";
+
+        $now = current_time('mysql');
+        $jobIds = $this->db->get_col($this->db->prepare($sql, $now, $limit));
@@
-        $lockSql = "UPDATE {$this->table}
-                    SET status = 'processing', locked_at = %s, attempts = attempts + 1
-                    WHERE id IN ($idsCsv)";
-        
-        $this->db->query($this->db->prepare($lockSql, $now));
+        $lockSql = "UPDATE {$this->table}
+                    SET status = 'processing', locked_at = %s, attempts = attempts + 1
+                    WHERE id IN ($idsCsv)";
+
+        $this->db->query($this->db->prepare($lockSql, $now));
@@
-    private function markAsFailed(Job $job, string $error): void
-    {
-        $maxAttempts = 3;
-        $status = $job->attempts >= $maxAttempts ? 'failed' : 'pending';
-
-        $this->db->update(
-            $this->table,
-            [
-                'status'        => $status,
-                'error_message' => $error,
-                'locked_at'     => null,
-            ],
-            ['id' => $job->id]
-        );
-    }
+    private function markAsFailed(Job $job, string $error): void
+    {
+        $maxAttempts = 3;
+
+        // If we've reached max attempts mark as failed permanently
+        if ($job->attempts >= $maxAttempts) {
+            $this->db->update(
+                $this->table,
+                [
+                    'status'        => 'failed',
+                    'error_message' => $error,
+                    'locked_at'     => null,
+                ],
+                ['id' => $job->id]
+            );
+
+            $this->logger->error('Job failed permanently after max attempts', ['job_id' => $job->id, 'error' => $error]);
+            return;
+        }
+
+        // Exponential backoff delays in seconds for attempts 1..N
+        $backoff = [60, 300, 900]; // 1m, 5m, 15m
+        $attemptIndex = max(0, min(count($backoff) - 1, $job->attempts - 1));
+        $delaySeconds = $backoff[$attemptIndex] ?? 60;
+
+        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
+
+        // Set status back to pending and schedule next available time via locked_at
+        $this->db->update(
+            $this->table,
+            [
+                'status'        => 'pending',
+                'error_message' => $error,
+                'locked_at'     => $availableAt,
+            ],
+            ['id' => $job->id]
+        );
+
+        $this->logger->warning('Job will be retried', ['job_id' => $job->id, 'next_try_at' => $availableAt, 'attempts' => $job->attempts, 'error' => $error]);
+    }
