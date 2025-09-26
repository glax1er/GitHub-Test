<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Journal Entry System</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 20px;
      background: #f4f6f9;
    }
    h1, h2 {
      text-align: center;
    }
    .entry-form {
      background: #fff;
      padding: 15px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.2);
      margin-bottom: 20px;
    }
    label {
      font-weight: bold;
      margin-right: 8px;
    }
    input, textarea {
      padding: 6px;
      border-radius: 4px;
      border: 1px solid #ccc;
      box-sizing: border-box;
    }
    input[type="date"] {
      width: 160px;
    }
    textarea {
      resize: none;
      width: 100%;
      margin-top: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      background: #fff;
      table-layout: fixed;
    }
    th, td {
      padding: 8px;
      border: 1px solid #ddd;
      text-align: center;
      vertical-align: top;
      word-wrap: break-word;
    }
    th {
      background: #007bff;
      color: white;
    }

    /* Fix action button alignment */
    #journalBody td:last-child {
      text-align: center;
      vertical-align: middle; /* removes that upward offset */
      padding: 0; /* removes extra top/bottom padding */
    }
    #journalBody td:last-child button {
      margin: 2px 0; /* keep button centered nicely */
    }

    /* Separator row for spacing */
    .separator-row td {
      border: none;
      height: 12px;   /* controls the space between entries */
      background: transparent;
    }


    td input {
      width: 100%;
      border: none;
      outline: none;
      background: transparent;
      text-align: center;
    }
    td input[type="text"] {
      text-align: left;
    }
    button {
      padding: 6px 10px;
      margin-top: 10px;
      background: #007bff;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
    }
    button:hover {
      background: #0056b3;
    }
    .remove-btn {
      background: #dc3545;
      padding: 4px 6px;
      font-size: 12px;
    }
    .remove-btn:hover {
      background: #a71d2a;
    }
    .delete-entry-btn {
      background: #dc3545;
      padding: 4px 8px;
      border-radius: 4px;
      cursor: pointer;
    }
    .delete-entry-btn:hover {
      background: #a71d2a;
    }
    .error {
      color: red;
      font-weight: bold;
    }
    .credit-cell {
      text-indent: 40px;
      text-align: left;
    }
    .debit-cell {
      text-align: left;
    }
    /* Comment row only under Account Title */
    .comment-row td {
      font-style: italic;
      text-align: left;
      border-top: none;
      border-bottom: 1px solid #ddd;
      white-space: normal;
      word-break: break-word;
      text-indent: 40px;
    }
    .indent {
      padding-left: 30px; 
    }
    .editable {
      cursor: pointer;
    }
    th:nth-child(2), td:nth-child(2) { text-align: left; } /* Account Title */
    th:nth-child(3), td:nth-child(3),
    th:nth-child(4), td:nth-child(4),
    th:nth-child(5), td:nth-child(5) { text-align: center; } /* Ref, Debit, Credit */
    th:last-child, td:last-child { width: 60px; } /* Action column small */
  </style>
</head>
<body>

  <h1>Journal Entry System</h1>

  <div class="entry-form">
    <!-- Date input -->
    <div>
      <label for="date">Date:</label>
      <input type="date" id="date" required>
    </div>

    <!-- Entry line form -->
    <table id="lineItems">
      <thead>
        <tr>
          <th>Account Title</th>
          <th style="text-align: center;">Reference No</th>
          <th>Debit</th>
          <th>Credit</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    
    <!-- Comment box -->
    <label for="comment">Comment:</label>
    <textarea id="comment" rows="2" placeholder="Comments or default as '#'"></textarea>
    
    <br>
    <button type="button" onclick="addRow()">Add Account Title</button>
    <button type="button" onclick="saveEntry()">Save Journal Entry</button>
    <p class="error" id="errorMsg"></p>
  </div>

  <div style="position: relative; text-align: center;">
    <h2 style="margin:0;">Journal Entries</h2>
    <button 
      style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);" 
      onclick="restartJournals()">
      Restart Entries
    </button>
  </div>

  <table id="journalTable">
    <thead>
      <tr>
        <th style="width:15%">Date</th>
        <th style="width:35%; text-align: center";>Account Title</th>
        <th style="width:15%">Ref No</th>
        <th style="width:15%">Debit</th>
        <th style="width:15%">Credit</th>
        <th style="width:5%"></th>
      </tr>
    </thead>
    <tbody id="journalBody"></tbody>
  </table>

    <script>
    async function restartJournals() {
      if (!confirm("Are you sure you want to restart? This will wipe ALL journal entries.")) return;

      const res = await fetch("restart.php", { method: "POST" });
      const data = await res.json();

      if (data.success) {
        alert("Journal reset successful.");
        loadEntries(); // refresh the table
      } else {
        alert("Reset failed: " + data.error);
      }
    }
    </script>

  <script>
    const lineItems = document.querySelector("#lineItems tbody");
    const journalBody = document.querySelector("#journalBody");
    const errorMsg = document.getElementById("errorMsg");

    // Add a new row to the form (default 2 rows not removable)
    function addRow(removable = true) {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td><input type="text" placeholder="e.g., Cash" required></td>
        <td><input type="text" placeholder="e.g., 123" required></td>
        <td><input type="number" step="0.01" value="0" oninput="lockCredit(this)"></td>
        <td><input type="number" step="0.01" value="0" oninput="lockDebit(this)"></td>
        <td>${removable ? '<button type="button" class="remove-btn" onclick="removeRow(this)">X</button>' : ''}</td>
      `;
      lineItems.appendChild(row);
    }

    // Remove row (only if removable)
    function removeRow(button) {
      button.closest("tr").remove();
    }

    // Lock credit if debit entered
    function lockCredit(debitInput) {
      const creditInput = debitInput.parentElement.nextElementSibling.querySelector("input");
      if (parseFloat(debitInput.value) > 0) {
        creditInput.value = 0;
        creditInput.disabled = true;
      } else {
        creditInput.disabled = false;
      }
    }

    // Lock debit if credit entered
    function lockDebit(creditInput) {
      const debitInput = creditInput.parentElement.previousElementSibling.querySelector("input");
      if (parseFloat(creditInput.value) > 0) {
        debitInput.value = 0;
        debitInput.disabled = true;
      } else {
        debitInput.disabled = false;
      }
    }

    // Save entry into journal table
    async function saveEntry() {
  const date = document.getElementById("date").value;
  let comment = document.getElementById("comment").value.trim() || "#";

  const rows = [...lineItems.querySelectorAll("tr")].map(r => {
    return {
      account_id: r.querySelector("td:nth-child(1) input").value.trim(), // change to real ID later
      ref: r.querySelector("td:nth-child(2) input").value.trim(),
      debit: parseFloat(r.querySelector("td:nth-child(3) input").value) || 0,
      credit: parseFloat(r.querySelector("td:nth-child(4) input").value) || 0,
    };
  });

  const res = await fetch("save_journal.php", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify({ date, comment, lines: rows })
  });
  const data = await res.json();

  if (data.success) {
    alert("Saved successfully!");
    loadEntries();
  } else {
    alert("Error: " + data.error);
  }
}

async function loadEntries() {
  const res = await fetch("get_journals.php");
  const data = await res.json();
  console.log("Loaded entries:", data);

  journalBody.innerHTML = "";

  data.forEach(entry => {
    entry.lines.forEach((line, index) => {
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${index === 0 ? entry.date : ""}</td>
        <td contenteditable="true" 
            data-type="account" 
            data-line="${line.line_id}" 
            class="${index > 0 ? "indent" : ""}">
          ${line.account_name}
        </td>
        <td contenteditable="true" data-type="ref" data-line="${line.line_id}">${line.ref}</td>
        <td contenteditable="true" data-type="debit" data-line="${line.line_id}">${line.debit > 0 ? line.debit.toFixed(2) : ""}</td>
        <td contenteditable="true" data-type="credit" data-line="${line.line_id}">${line.credit > 0 ? line.credit.toFixed(2) : ""}</td>
        <td>
          ${index === 0 ? `<button onclick="deleteEntry(${entry.id})">Delete</button>` : ""}
        </td>
      `;
      journalBody.appendChild(row);
    });

    // Add the comment row (editable too)
    const commentRow = document.createElement("tr");
    commentRow.innerHTML = `
      <td></td>
      <td colspan="4" contenteditable="true" data-type="comment" data-entry="${entry.id}" style="padding-left: 60px; font-style: italic; color: black;">
        ${entry.comment}
      </td>
      <td></td>
    `;
    journalBody.appendChild(commentRow);

    // Spacer row
    const spacer = document.createElement("tr");
    spacer.innerHTML = `<td colspan="6" style="height:10px; border-bottom:1px solid #ddd;"></td>`;
    journalBody.appendChild(spacer);
  });

  // Attach save handler
  document.querySelectorAll("[contenteditable]").forEach(cell => {
    cell.addEventListener("blur", async (e) => {
      const value = e.target.innerText.trim();
      const type = e.target.dataset.type;
      const lineId = e.target.dataset.line;
      const entryId = e.target.dataset.entry;

      const payload = { type, value, lineId, entryId };

      const res = await fetch("update_entries.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!data.success) {
        alert("Update failed: " + data.error);
      }
    });
  });
}



window.addEventListener("DOMContentLoaded", loadEntries);

    // Delete whole journal entry
    function deleteEntry(button) {
      let row = button.closest("tr");
      let next = row.nextElementSibling;
      while (next && !next.querySelector(".delete-entry-btn")) {
        let toDelete = next;
        next = next.nextElementSibling;
        toDelete.remove();
      }
      row.remove();
    }

    // Editing in journal
    journalBody.addEventListener("click", function(e) {
      if (e.target.classList.contains("editable")) {
        let cell = e.target;
        let type = cell.dataset.type;
        let currentValue = cell.textContent;

        if (type === "comment") {
          currentValue = currentValue.replace("Comment: ", "");
        }

        cell.innerHTML = `<input type="${(type === "debit" || type === "credit") ? "number" : "text"}" value="${currentValue}">`;
        let input = cell.querySelector("input");

        // Focus cursor at end
        input.focus();
        input.setSelectionRange(input.value.length, input.value.length);

        input.addEventListener("blur", () => saveEdit(cell, input.value, type));
        input.addEventListener("keydown", (ev) => {
          if (ev.key === "Enter") saveEdit(cell, input.value, type);
        });
      }
    });

    function saveEdit(cell, newValue, type) {
      newValue = newValue.trim();
      if (type === "comment") {
        cell.textContent = "Comment: " + (newValue || "#");
      } else if (type === "debit" || type === "credit") {
        let num = parseFloat(newValue);
        cell.textContent = isNaN(num) ? "" : num.toFixed(2);
      } else {
        cell.textContent = newValue;
      }
      cell.classList.add("editable");
      cell.dataset.type = type;
    }

    // Initialize with 2 default rows
    addRow(false);
    addRow(false);
  </script>

</body>
</html>
