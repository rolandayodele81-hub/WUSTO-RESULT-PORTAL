(function () {
  "use strict";

  var area = document.getElementById("resultArea");
  if (!area) return;

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function (m) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m];
    });
  }

  function render(data) {
    if (!data.ok) {
      area.innerHTML = '<div class="alert alert-danger">' + escapeHtml(data.error) + "</div>";
      return;
    }

    var s = data.student;
    var html = '<div class="card bg-secondary bg-opacity-10 border-0 shadow-lg mb-4"><div class="card-body p-4">';
    html += "<h2 class=\"h5 mb-1\">" + escapeHtml(s.firstName + " " + s.lastName) + "</h2>";
    html += '<p class="text-muted small mb-3">' + escapeHtml(s.department) + " · " + escapeHtml(s.level) + " · " + escapeHtml(s.matric) + "</p>";

    if (data.cgpa !== null && data.cgpa !== undefined) {
      html += '<div class="d-flex align-items-baseline gap-2 mb-4"><span class="display-5 fw-bold">' + data.cgpa.toFixed(2) + '</span><span class="text-muted">/ 5.00 CGPA</span></div>';
    }

    if (!data.semesters.length) {
      html += '<p class="text-muted mb-0">No published results yet. Check back once your lecturers submit and the registrar approves your scores.</p>';
    }

    data.semesters.slice().reverse().forEach(function (sem) {
      html += '<div class="mb-4"><div class="d-flex justify-content-between align-items-center mb-2">';
      html += "<h3 class=\"h6 mb-0\">" + escapeHtml(sem.session) + " · " + escapeHtml(sem.semester) + "</h3>";
      html += '<span class="badge bg-warning text-dark">GPA ' + sem.gpa.toFixed(2) + "</span></div>";
      html += '<div class="table-responsive"><table class="table table-dark table-sm align-middle mb-0">';
      html += "<thead><tr><th>Code</th><th>Course</th><th>Units</th><th>CA</th><th>Exam</th><th>Total</th><th>Grade</th></tr></thead><tbody>";
      sem.courses.forEach(function (c) {
        html += "<tr><td>" + escapeHtml(c.code) + "</td><td>" + escapeHtml(c.title) + "</td><td>" + c.units +
          "</td><td>" + c.ca + "</td><td>" + c.exam + "</td><td>" + c.total +
          '</td><td class="grade-' + escapeHtml(c.grade) + '">' + escapeHtml(c.grade) + "</td></tr>";
      });
      html += "</tbody></table></div></div>";
    });

    html += "</div></div>";
    area.innerHTML = html;
  }

  function fetchResult(params) {
    var query = params ? "?" + new URLSearchParams(params).toString() : "";
    area.innerHTML = '<p class="text-muted">Checking…</p>';
    fetch("../api/check-result.php" + query, { credentials: "same-origin" })
      .then(function (r) { return r.json(); })
      .then(render)
      .catch(function () {
        area.innerHTML = '<div class="alert alert-danger">Something went wrong. Please try again in a moment.</div>';
      });
  }

  if (window.WU_LOGGED_IN) {
    fetchResult(null);
  } else {
    var form = document.getElementById("guestForm");
    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        fetchResult({
          matric: document.getElementById("matric").value.trim(),
          last_name: document.getElementById("lastName").value.trim()
        });
      });
    }
  }
})();
