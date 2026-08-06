(function () {
  "use strict";

  /* =========================================================
     0. Demo "database" — persisted in localStorage so accounts
        created just now, or the seeded demo students, survive
        page reloads. This is a front-end only preview: swap
        `db.*` for real fetch() calls to your backend/API later.
     ========================================================= */

  var DB_KEY = "wesleyPortal.students.v1";
  var SESSION_KEY = "wesleyPortal.session.v1";
  var DEMO_PASSWORD = "wesley2026";
  var studentsCache = null;

  var GRADE_POINTS = { A: 5, B: 4, C: 3, D: 2, E: 1, F: 0 };

  function gradeFor(total) {
    if (total >= 70) return "A";
    if (total >= 60) return "B";
    if (total >= 50) return "C";
    if (total >= 45) return "D";
    if (total >= 40) return "E";
    return "F";
  }

  function course(code, title, units, ca, exam) {
    var total = ca + exam;
    return { code: code, title: title, units: units, ca: ca, exam: exam, total: total, grade: gradeFor(total) };
  }

  function semesterGpa(courses) {
    var totalPoints = 0, totalUnits = 0;
    courses.forEach(function (c) {
      totalPoints += GRADE_POINTS[c.grade] * c.units;
      totalUnits += c.units;
    });
    return totalUnits ? totalPoints / totalUnits : 0;
  }

  function seedStudents() {
    return [
      {
        matric: "WU/2021/0143",
        password: DEMO_PASSWORD,
        firstName: "Adaeze",
        lastName: "Okonkwo",
        department: "Computer Science",
        level: "300 Level",
        semesters: [
          {
            session: "2023/2024", semester: "First Semester",
            courses: [
              course("CSC301", "Data Structures & Algorithms", 3, 24, 52),
              course("CSC303", "Operating Systems", 3, 22, 48),
              course("CSC305", "Database Management Systems", 2, 26, 58),
              course("MTH301", "Numerical Methods", 3, 18, 40),
              course("GST301", "Entrepreneurship Studies", 2, 27, 60)
            ]
          },
          {
            session: "2023/2024", semester: "Second Semester",
            courses: [
              course("CSC302", "Software Engineering", 3, 25, 55),
              course("CSC304", "Computer Networks", 3, 23, 44),
              course("CSC306", "Web Technologies", 2, 28, 62),
              course("STA302", "Probability & Statistics II", 3, 20, 46)
            ]
          }
        ]
      },
      {
        matric: "WU/2020/0871",
        password: DEMO_PASSWORD,
        firstName: "Tobiloba",
        lastName: "Adewale",
        department: "Accounting",
        level: "400 Level",
        semesters: [
          {
            session: "2022/2023", semester: "First Semester",
            courses: [
              course("ACC401", "Advanced Financial Accounting", 3, 21, 46),
              course("ACC403", "Taxation", 3, 24, 50),
              course("ACC405", "Auditing", 2, 19, 38),
              course("BUS401", "Business Law", 2, 26, 55)
            ]
          },
          {
            session: "2023/2024", semester: "First Semester",
            courses: [
              course("ACC411", "Public Sector Accounting", 3, 25, 57),
              course("ACC413", "Management Accounting", 3, 23, 49),
              course("ACC415", "Forensic Accounting", 2, 20, 42),
              course("BUS411", "Strategic Management", 2, 27, 61)
            ]
          }
        ]
      },
      {
        matric: "WU/2022/1290",
        password: DEMO_PASSWORD,
        firstName: "Miracle",
        lastName: "Eze",
        department: "Mass Communication",
        level: "200 Level",
        semesters: [
          {
            session: "2023/2024", semester: "First Semester",
            courses: [
              course("MAC201", "Reporting & News Writing", 3, 26, 56),
              course("MAC203", "Broadcast Journalism", 3, 24, 51),
              course("MAC205", "Media Law & Ethics", 2, 19, 36),
              course("GST201", "Philosophy & Logic", 2, 25, 52)
            ]
          }
        ]
      }
    ];
  }

  function loadStudents() {
    if (studentsCache) return studentsCache;

    try {
      var raw = window.localStorage.getItem(DB_KEY);
      if (raw) {
        studentsCache = JSON.parse(raw);
        return studentsCache;
      }
    } catch (e) { /* storage unavailable */ }

    try {
      var request = new XMLHttpRequest();
      request.open("GET", "data/students.json", false);
      request.send(null);
      if (request.status >= 200 && request.status < 300) {
        var remoteStudents = JSON.parse(request.responseText);
        if (Array.isArray(remoteStudents)) {
          studentsCache = remoteStudents;
          saveStudents(studentsCache);
          return studentsCache;
        }
      }
    } catch (e) { /* fallback to seeded demo data */ }

    var seeded = seedStudents();
    studentsCache = seeded;
    saveStudents(seeded);
    return seeded;
  }

  function saveStudents(list) {
    try { window.localStorage.setItem(DB_KEY, JSON.stringify(list)); } catch (e) { /* ignore */ }
  }

  function findStudent(matric) {
    var normalized = (matric || "").trim().toUpperCase();
    return loadStudents().filter(function (s) { return s.matric.toUpperCase() === normalized; })[0] || null;
  }

  function saveSession(matric) {
    try { window.localStorage.setItem(SESSION_KEY, matric); } catch (e) { /* ignore */ }
  }
  function readSession() {
    try { return window.localStorage.getItem(SESSION_KEY); } catch (e) { return null; }
  }
  function clearSession() {
    try { window.localStorage.removeItem(SESSION_KEY); } catch (e) { /* ignore */ }
  }

  /* =========================================================
     1. View routing
     ========================================================= */

  var body = document.body;

  function setView(name) {
    body.dataset.view = name;
    if (name === "login" || name === "register") {
      switchTab(name);
    }
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function refreshHeader() {
    var session = readSession();
    var student = session ? findStudent(session) : null;
    document.getElementById("sessionPill").hidden = !student;
    document.getElementById("navDashboard").hidden = !student;
    document.getElementById("navLogout").hidden = !student;
    document.getElementById("navLogin").hidden = !!student;
    document.getElementById("navRegister").hidden = !!student;
    if (student) {
      document.getElementById("sessionName").textContent = student.firstName + " " + student.lastName;
    }
  }

  document.querySelectorAll("[data-nav]").forEach(function (el) {
    el.addEventListener("click", function () {
      var target = el.dataset.nav;
      if (target === "logout") {
        clearSession();
        refreshHeader();
        showToast("You've been signed out.");
        setView("home");
        return;
      }
      if (target === "dashboard") {
        var session = readSession();
        if (!session || !findStudent(session)) { setView("login"); return; }
        renderDashboard(findStudent(session));
        setView("dashboard");
        return;
      }
      setView(target);
    });
  });

  document.querySelectorAll("[data-fill]").forEach(function (chip) {
    chip.addEventListener("click", function () {
      setView("login");
      document.getElementById("loginMatric").value = chip.dataset.fill;
      document.getElementById("loginMatric").focus();
    });
  });

  /* =========================================================
     2. Auth tabs
     ========================================================= */

  function switchTab(name) {
    document.getElementById("tabLogin").classList.toggle("is-active", name === "login");
    document.getElementById("tabRegister").classList.toggle("is-active", name === "register");
    document.getElementById("tabLogin").setAttribute("aria-selected", name === "login");
    document.getElementById("tabRegister").setAttribute("aria-selected", name === "register");
    document.getElementById("loginForm").classList.toggle("is-active", name === "login");
    document.getElementById("registerForm").classList.toggle("is-active", name === "register");
  }

  document.getElementById("tabLogin").addEventListener("click", function () { setView("login"); });
  document.getElementById("tabRegister").addEventListener("click", function () { setView("register"); });

  /* =========================================================
     3. Login
     ========================================================= */

  document.getElementById("loginForm").addEventListener("submit", function (event) {
    event.preventDefault();
    var errorEl = document.getElementById("loginError");
    errorEl.hidden = true;

    var matric = document.getElementById("loginMatric").value.trim();
    var password = document.getElementById("loginPassword").value;
    var student = findStudent(matric);

    if (!student || student.password !== password) {
      errorEl.textContent = "We couldn't match that matric number and password. Double-check and try again.";
      errorEl.hidden = false;
      return;
    }

    saveSession(student.matric);
    refreshHeader();
    renderDashboard(student);
    setView("dashboard");
    showToast("Welcome back, " + student.firstName + ".");
    this.reset();
  });

  /* =========================================================
     4. Register
     ========================================================= */

  document.getElementById("registerForm").addEventListener("submit", function (event) {
    event.preventDefault();
    var errorEl = document.getElementById("registerError");
    var successEl = document.getElementById("registerSuccess");
    errorEl.hidden = true;
    successEl.hidden = true;

    var firstName = document.getElementById("regFirst").value.trim();
    var lastName = document.getElementById("regLast").value.trim();
    var matric = document.getElementById("regMatric").value.trim();
    var department = document.getElementById("regDept").value.trim();
    var level = document.getElementById("regLevel").value;
    var password = document.getElementById("regPassword").value;

    if (!firstName || !lastName || !matric || !department || password.length < 8) {
      errorEl.textContent = "Please complete every field. Passwords need at least 8 characters.";
      errorEl.hidden = false;
      return;
    }

    if (findStudent(matric)) {
      errorEl.textContent = "An account already exists for that matric number. Try signing in instead.";
      errorEl.hidden = false;
      return;
    }

    var students = loadStudents();
    students.push({
      matric: matric.toUpperCase(),
      password: password,
      firstName: firstName,
      lastName: lastName,
      department: department,
      level: level,
      semesters: []
    });
    saveStudents(students);

    successEl.textContent = "Account created. You can sign in as soon as your first result is published.";
    successEl.hidden = false;
    this.reset();

    window.setTimeout(function () {
      setView("login");
      document.getElementById("loginMatric").value = matric.toUpperCase();
      document.getElementById("loginMatric").focus();
    }, 900);
  });

  /* =========================================================
     5. Dashboard rendering
     ========================================================= */

  function renderDashboard(student) {
    document.getElementById("dashName").textContent = student.firstName + " " + student.lastName;
    document.getElementById("dashMeta").textContent = student.department + " · " + student.level + " · " + student.matric;

    var allCourses = [];
    var gpas = [];
    student.semesters.forEach(function (sem) {
      allCourses = allCourses.concat(sem.courses);
      gpas.push(semesterGpa(sem.courses));
    });

    var totalPoints = 0, totalUnits = 0;
    allCourses.forEach(function (c) { totalPoints += GRADE_POINTS[c.grade] * c.units; totalUnits += c.units; });
    var cgpa = totalUnits ? totalPoints / totalUnits : 0;

    document.getElementById("cgpaValue").textContent = cgpa.toFixed(2);
    var circumference = 377;
    var offset = circumference - (cgpa / 5) * circumference;
    var fill = document.getElementById("gaugeFill");
    fill.style.strokeDashoffset = String(circumference);
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () { fill.style.strokeDashoffset = String(offset); });
    });

    document.getElementById("statSemesters").textContent = String(student.semesters.length);
    document.getElementById("statCourses").textContent = String(allCourses.length);
    document.getElementById("statUnits").textContent = String(totalUnits);
    document.getElementById("statBestGpa").textContent = (gpas.length ? Math.max.apply(null, gpas) : 0).toFixed(2);

    var body = document.getElementById("dashBody");
    body.innerHTML = "";

    if (!student.semesters.length) {
      var empty = document.createElement("div");
      empty.className = "empty-state";
      empty.innerHTML = "<h3>No results published yet</h3><p>As soon as your lecturers submit and the registrar approves your scores, they'll appear here automatically — no need to check back manually.</p>";
      body.appendChild(empty);
      return;
    }

    student.semesters.slice().reverse().forEach(function (sem, index) {
      var gpa = semesterGpa(sem.courses);
      var card = document.createElement("article");
      card.className = "sem-card tilt-card" + (index === 0 ? " is-open" : "");

      var head = document.createElement("div");
      head.className = "sem-head";
      head.innerHTML =
        '<div><h3>' + sem.semester + '</h3><p class="sem-sub">' + sem.session + ' academic session</p></div>' +
        '<div class="sem-meta"><span class="sem-gpa">GPA ' + gpa.toFixed(2) + '</span>' +
        '<svg class="sem-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></div>';
      head.addEventListener("click", function () { card.classList.toggle("is-open"); });

      var tableWrap = document.createElement("div");
      tableWrap.className = "sem-table-wrap";
      var table = document.createElement("table");
      table.className = "sem-table";
      var thead = "<thead><tr><th>Code</th><th>Course</th><th>Units</th><th>CA</th><th>Exam</th><th>Total</th><th>Grade</th></tr></thead>";
      var rows = sem.courses.map(function (c) {
        return "<tr><td>" + c.code + "</td><td>" + c.title + "</td>" +
          '<td class="num">' + c.units + '</td><td class="num">' + c.ca + '</td><td class="num">' + c.exam + '</td>' +
          '<td class="num">' + c.total + '</td><td><span class="grade-pill grade-' + c.grade + '">' + c.grade + "</span></td></tr>";
      }).join("");
      table.innerHTML = thead + "<tbody>" + rows + "</tbody>";
      tableWrap.appendChild(table);

      card.appendChild(head);
      card.appendChild(tableWrap);
      body.appendChild(card);
    });

    applyTilt(body.querySelectorAll(".tilt-card"));
  }

  /* =========================================================
     6. Guest quick-check (flip card, most recent semester only)
     ========================================================= */

  var guestModal = document.getElementById("guestModal");
  var flipCard = document.getElementById("flipCard");

  document.querySelectorAll("[data-open]").forEach(function (btn) {
    btn.addEventListener("click", function () { openModal(guestModal); });
  });
  document.querySelectorAll("[data-close]").forEach(function (btn) {
    btn.addEventListener("click", function () { closeModal(btn.closest(".modal-backdrop")); });
  });
  guestModal.addEventListener("click", function (e) { if (e.target === guestModal) closeModal(guestModal); });
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeModal(guestModal); });

  function openModal(modal) {
    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    body.classList.add("has-modal");
    window.setTimeout(function () {
      var field = modal.querySelector("input, select, button");
      if (field) field.focus();
    }, 30);
  }
  function closeModal(modal) {
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    body.classList.remove("has-modal");
    window.setTimeout(function () {
      flipCard.classList.remove("is-flipped");
      document.getElementById("guestForm").reset();
    }, 250);
  }

  document.getElementById("guestForm").addEventListener("submit", function (event) {
    event.preventDefault();
    var matric = document.getElementById("guestMatric").value.trim();
    var student = findStudent(matric);
    var resultEl = document.getElementById("guestResult");

    if (!student || !student.semesters.length) {
      resultEl.innerHTML =
        '<div class="guest-result-head"><p>' + (student ? "No published result yet" : "No matching student") + '</p><h3>' +
        (student ? student.firstName + " " + student.lastName : matric.toUpperCase()) + "</h3></div>" +
        '<p style="text-align:center;color:var(--cream-muted);font-size:0.85rem;">' +
        (student ? "Check back once your semester result is published, or sign in to see your full record." : "Double-check the matric number, or create an account to get started.") +
        "</p>" +
        '<div class="flip-back-actions" style="margin-top:1.25rem;">' +
        '<button class="button button-outline" type="button" data-flip-back>Try again</button>' +
        '</div>';
    } else {
      var latest = student.semesters[student.semesters.length - 1];
      var gpa = semesterGpa(latest.courses);
      var units = latest.courses.reduce(function (sum, c) { return sum + c.units; }, 0);
      resultEl.innerHTML =
        '<div class="guest-result-head"><p>' + latest.session + " · " + latest.semester + '</p><h3>' + student.firstName + " " + student.lastName + "</h3></div>" +
        '<div class="guest-rows">' +
        '<div class="guest-row"><span>Matric number</span><strong>' + student.matric + '</strong></div>' +
        '<div class="guest-row"><span>Semester GPA</span><strong>' + gpa.toFixed(2) + ' / 5.00</strong></div>' +
        '<div class="guest-row"><span>Units this semester</span><strong>' + units + '</strong></div>' +
        '<div class="guest-row"><span>Courses graded</span><strong>' + latest.courses.length + '</strong></div>' +
        "</div>" +
        '<div class="flip-back-actions">' +
        '<button class="button button-outline" type="button" data-flip-back>Check another</button>' +
        '<button class="button button-gold" type="button" data-goto-login>Sign in for full record</button>' +
        "</div>";
    }

    flipCard.classList.add("is-flipped");

    resultEl.querySelector("[data-flip-back]").addEventListener("click", function () {
      flipCard.classList.remove("is-flipped");
      window.setTimeout(function () { document.getElementById("guestForm").reset(); }, 350);
    });
    var loginBtn = resultEl.querySelector("[data-goto-login]");
    if (loginBtn) {
      loginBtn.addEventListener("click", function () {
        closeModal(guestModal);
        setView("login");
        document.getElementById("loginMatric").value = student.matric;
      });
    }
  });

  /* =========================================================
     7. Toast
     ========================================================= */

  var toast = document.getElementById("toast");
  var toastTimer = null;
  function showToast(message) {
    toast.textContent = message;
    toast.classList.add("is-visible");
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(function () { toast.classList.remove("is-visible"); }, 3200);
  }

  /* =========================================================
     8. 3D pointer tilt for cards
     ========================================================= */

  function applyTilt(nodeList) {
    nodeList.forEach(function (card) {
      if (card.dataset.tiltBound) return;
      card.dataset.tiltBound = "true";
      card.addEventListener("pointermove", function (event) {
        var rect = card.getBoundingClientRect();
        var x = event.clientX - rect.left - rect.width / 2;
        var y = event.clientY - rect.top - rect.height / 2;
        var rotateX = (y / rect.height) * -8;
        var rotateY = (x / rect.width) * 8;
        card.style.transform = "perspective(900px) rotateX(" + rotateX + "deg) rotateY(" + rotateY + "deg) translateY(-3px)";
      });
      card.addEventListener("pointerleave", function () {
        card.style.transform = "perspective(900px) rotateX(0deg) rotateY(0deg) translateY(0)";
      });
    });
  }

  applyTilt(document.querySelectorAll(".tilt-card"));

  /* =========================================================
     9. Ambient particle canvas (cinematic backdrop)
     ========================================================= */

  (function particles() {
    var canvas = document.getElementById("fx");
    if (!canvas || !canvas.getContext) return;
    var ctx = canvas.getContext("2d");
    var particlesList = [];
    var reduced = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    function resize() {
      canvas.width = window.innerWidth * dpr;
      canvas.height = window.innerHeight * dpr;
      canvas.style.width = window.innerWidth + "px";
      canvas.style.height = window.innerHeight + "px";
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }

    function seed() {
      var count = Math.round((window.innerWidth * window.innerHeight) / 26000);
      particlesList = [];
      for (var i = 0; i < count; i++) {
        particlesList.push({
          x: Math.random() * window.innerWidth,
          y: Math.random() * window.innerHeight,
          r: Math.random() * 1.6 + 0.4,
          vy: Math.random() * 0.18 + 0.04,
          vx: (Math.random() - 0.5) * 0.05,
          hue: Math.random() > 0.6 ? "gold" : "cream",
          alpha: Math.random() * 0.5 + 0.15
        });
      }
    }

    function tick() {
      ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
      particlesList.forEach(function (p) {
        p.y -= p.vy;
        p.x += p.vx;
        if (p.y < -4) { p.y = window.innerHeight + 4; p.x = Math.random() * window.innerWidth; }
        ctx.beginPath();
        ctx.fillStyle = p.hue === "gold" ? "rgba(242,193,78," + p.alpha + ")" : "rgba(248,246,240," + (p.alpha * 0.6) + ")";
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
      });
      if (!reduced) window.requestAnimationFrame(tick);
    }

    resize();
    seed();
    if (!reduced) window.requestAnimationFrame(tick);
    else tick();

    var resizeTimer;
    window.addEventListener("resize", function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () { resize(); seed(); }, 200);
    });
  })();

  /* =========================================================
     10. Boot
     ========================================================= */

  document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = new Date().getFullYear(); });

  loadStudents(); // ensure demo data + storage exist
  refreshHeader();

  var existingSession = readSession();
  if (existingSession && findStudent(existingSession)) {
    // returning, already-signed-in student — stay on home but header reflects session
  }
})();
