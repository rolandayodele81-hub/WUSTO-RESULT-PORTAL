WESLEY PORTAL — ADMIN RESULT UPLOAD + FAST RESULT CHECKER
===========================================================

WHY THIS IS A NEW SET OF FILES, NOT AN EDIT TO THE CINEMATIC DEMO
-------------------------------------------------------------------
The static index.html/script.js portal from before stores everything in
the browser's localStorage. That's local to one device — an admin can't
publish into it, and 1,500 students can't share it. To have one admin
upload results that every student then sees, results have to live in
your real MySQL database. These files wire straight into the PHP/MySQL
backend you already uploaded (schema.sql, login.php, functions.php, etc).

WHERE EACH FILE GOES (same layout as your existing "portal" folder)
-------------------------------------------------------------------
portal/includes/schema-patch.sql        -> run once against your database
portal/includes/functions-additions.php -> copy the functions into your
                                            existing includes/functions.php
portal/admin/upload-results.php         -> new file
portal/api/check-result.php             -> new file
portal/student/check-result.php         -> new file
portal/assets/js/result-client.js       -> new file

SETUP STEPS
-----------
1. Back up your database, then run schema-patch.sql against wesley_portal.
   It adds a unique key so re-uploading the same result updates it
   instead of duplicating it, an index for fast lookups, and an
   uploaded_by column for an audit trail.

2. Open includes/functions.php and paste in everything from
   functions-additions.php (grade_for, grade_point, require_any_role,
   and three small cache helpers).

3. Drop the four new files into admin/, api/, student/, assets/js/.

4. (Optional but recommended) Install the APCu PHP extension:
      sudo apt install php-apcu   (Ubuntu/Debian)
   The cache helpers check for it automatically — if it's missing,
   the code just skips caching, nothing breaks.

HOW IT WORKS
------------
- Admin visits admin/upload-results.php, uploads a CSV
  (matric_number,session,semester,course_code,course_title,units,ca_score,exam_score),
  and every listed result is published in one transaction. Grades and
  GPA/CGPA are computed automatically — nothing to type by hand.

- Students visit student/check-result.php:
    * Signed in -> their full transcript and running CGPA load instantly,
      no typing required.
    * Not signed in -> a quick matric number + surname check shows just
      their latest published semester (kept deliberately limited, so a
      matric number alone can't pull up someone else's scores).

- Both paths hit api/check-result.php, which does one indexed query,
  computes GPA/CGPA in PHP, and caches the result per student for 45
  seconds. The admin upload clears that cache for any student it
  touches, so newly-published results show immediately.

HANDLING ~1,500 CONCURRENT STUDENTS
------------------------------------
The query itself is cheap (one indexed JOIN, no N+1 lookups), so the
main job is making sure the server around it isn't the bottleneck:

1. Run the schema patch — it's the single biggest lever. Without the
   index, a "students checking results the moment they're released"
   spike turns into a full table scan per request.

2. Turn on PHP OPcache (php.ini):
      opcache.enable=1
      opcache.memory_consumption=128
      opcache.max_accelerated_files=10000
   This alone can cut PHP response time several times over.

3. Tune PHP-FPM (www.conf):
      pm = dynamic
      pm.max_children = 80        ; ~good starting point on a 4GB server
      pm.max_requests = 500       ; recycle workers to avoid memory creep
   Each worker handles one request at a time, so max_children roughly
   caps how many students can be served in the same instant.

4. MySQL: make sure innodb_buffer_pool_size is big enough to hold your
   results/courses/students tables in memory (a few hundred MB is
   plenty at this size), and leave max_connections at its default
   (151) unless PHP-FPM's max_children is set higher than that.

5. Serve assets/css, assets/js and the crest image with far-future
   cache headers (or put them behind a CDN/Cloudflare). Static files
   should never touch PHP or MySQL at all.

6. The API already rate-limits anonymous guest lookups (20 per minute
   per session) so a burst of repeated typos or scraping can't amplify
   into extra database load.

7. Before go-live, load-test it — e.g. with k6 or ab:
      ab -n 5000 -c 200 "https://yourdomain/portal/api/check-result.php?matric=WU/2021/0143&last_name=Okonkwo"
   1,500 concurrent students on a small-to-mid VPS (2–4 vCPU, 4–8GB RAM)
   is comfortably within reach once the index and OPcache are in place —
   you don't need queues or microservices for this scale.

CUSTOMIZING
-----------
- Grading scale lives in grade_for() in functions-additions.php.
- Which roles can upload results: edit the require_any_role([...]) list
  at the top of admin/upload-results.php.
- Cache TTL: the "45" in result_cache_set($cacheKey, $payload, 45)
  inside api/check-result.php.
