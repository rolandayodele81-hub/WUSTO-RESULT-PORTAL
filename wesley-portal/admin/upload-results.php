<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Result Upload — Wesley University</title>
  <style>
    :root { color-scheme: dark; --bg:#07111f; --panel:#101a2c; --text:#f7efe2; --muted:#b7bfca; --accent:#d4a017; }
    * { box-sizing: border-box; }
    body { margin:0; font-family:Inter, Arial, sans-serif; background:linear-gradient(135deg, var(--bg), #12263d); color:var(--text); }
    .wrap { max-width: 900px; margin:0 auto; padding:2.5rem 1.1rem 3rem; }
    .card { background: var(--panel); border:1px solid rgba(255,255,255,.08); border-radius:1rem; padding:1.2rem; box-shadow:0 20px 40px rgba(0,0,0,.2); }
    form { display:grid; gap:1rem; margin-top:1rem; }
    input, button { padding:.85rem 1rem; border-radius:.8rem; border:1px solid rgba(255,255,255,.12); font:inherit; }
    input[type="file"] { background: rgba(255,255,255,.05); color: var(--text); }
    button { background: var(--accent); color:#211402; font-weight:700; cursor:pointer; }
    .muted { color: var(--muted); }
    pre { background: rgba(255,255,255,.05); padding: 1rem; border-radius: .8rem; overflow-x:auto; }
    .success { color:#86f2bf; }
    .warning { color:#ffd56d; }
    .error { color:#ff9aa3; }
  </style>
</head>
<body>
  <main class="wrap">
    <div class="card">
      <h1 style="margin-top:0;">Upload student results</h1>
      <p class="muted">Upload a CSV file with the headers shown below. The demo will publish those results into the browser’s local data store so the portal and checker update immediately.</p>
      <form id="uploadForm">
        <input type="file" id="resultsCsv" accept=".csv" required>
        <button type="submit">Publish results</button>
      </form>

      <div id="summary" class="muted" style="margin-top:1rem;"></div>
      <h3 style="margin-top:1.35rem;">Expected CSV format</h3>
      <pre>matric_number,session,semester,course_code,course_title,units,ca_score,exam_score
WU/2021/0143,2023/2024,First Semester,CSC401,Artificial Intelligence,3,20,55</pre>
      <p class="muted"><a href="../student/check-result.php" style="color:#ffd56d;">Open the live checker</a> after uploading.</p>
    </div>
  </main>

  <script>
    const STORAGE_KEY = 'wesleyPortal.students.v1';
    const form = document.getElementById('uploadForm');
    const summary = document.getElementById('summary');

    function readStudents() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
      } catch (error) {
        return [];
      }
    }

    function writeStudents(students) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(students));
    }

    function parseCsv(text) {
      return text.trim().split(/\r?\n/).map((line) => line.split(',').map((cell) => cell.trim()));
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const file = document.getElementById('resultsCsv').files[0];
      if (!file) {
        summary.innerHTML = '<span class="error">Choose a CSV file first.</span>';
        return;
      }

      const text = await file.text();
      const rows = parseCsv(text);
      const students = readStudents();
      const [header, ...dataRows] = rows;

      let inserted = 0;
      let failed = 0;
      const issues = [];

      dataRows.forEach((row) => {
        if (!row.join('').trim()) return;
        if (row.length < 8) {
          failed += 1;
          issues.push('A row did not contain all required CSV values.');
          return;
        }

        const [matric, session, semester, courseCode, courseTitle, units, ca, exam] = row;
        const student = students.find((entry) => entry.matric.toUpperCase() === matric.toUpperCase());
        if (!student) {
          failed += 1;
          issues.push(`No student found for ${matric}.`);
          return;
        }

        const semesterEntry = student.semesters.find((entry) => entry.session === session && entry.semester === semester);
        const total = Number(ca) + Number(exam);
        const grade = total >= 70 ? 'A' : total >= 60 ? 'B' : total >= 50 ? 'C' : total >= 45 ? 'D' : total >= 40 ? 'E' : 'F';
        const course = { code: courseCode.toUpperCase(), title: courseTitle, units: Number(units), ca: Number(ca), exam: Number(exam), total, grade };

        if (semesterEntry) {
          const existingCourse = semesterEntry.courses.find((entry) => entry.code.toUpperCase() === course.code);
          if (existingCourse) {
            Object.assign(existingCourse, course);
          } else {
            semesterEntry.courses.push(course);
          }
        } else {
          student.semesters.push({ session, semester, courses: [course] });
        }

        inserted += 1;
      });

      writeStudents(students);
      summary.innerHTML = '<div class="' + (failed ? 'warning' : 'success') + '"><strong>' + inserted + '</strong> result row(s) published. ' + (failed ? '<strong>' + failed + '</strong> row(s) were skipped.' : '') + '</div>';
      if (issues.length) {
        summary.innerHTML += '<ul class="muted" style="margin-top:.8rem;">' + issues.map((issue) => '<li>' + issue + '</li>').join('') + '</ul>';
      }
    });
  </script>
</body>
</html>
