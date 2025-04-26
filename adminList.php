<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style type="text/css">
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: Arial, sans-serif;
        background-color: #ff6d0d;
        color: white;
    }

    header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background-color: #CF5300;
    }

    header .logo {
        font-size: 24px;
        font-weight: bold;
        margin-left: 30px;
        margin-top: 3px;
    }

    header nav a {
        margin: 0 15px;
        text-decoration: none;
        color: #FFC49C;
        font-size: 20px;
        font-family: Purple Smile;
        letter-spacing: .5px;
    }

    header nav a:hover {
        color: #923a00;
        transform: scale(1.1);
    }

    .active {
        font-size: 23px;
        color: white;
    }

    .tabs {
        display: flex;
        justify-content: center;
        margin-right: 65%;
        margin-bottom: -.65%;
        margin-top: 5%;
    }

    .tab-button {
        width: 180px;
        background-color: #FFEFE4;
        color: #7D3200;
        border: none;
        padding: 10px;
        cursor: pointer;
        font-family: Purple Smile;
        border-top-left-radius: 10px;
        border-top-right-radius: 10px;
    }

    .tab-button.active {
        background-color: #7D3200;
        color: #FFEFE4;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    table {
        width: 95%;
        padding: 10px;
        margin-left: 2.5%;
        font-family: Purple Smile;
        border-radius: 20px;
        border-collapse: separate;
        border-spacing: 0;
        overflow: hidden;
    }

    td, th {
        padding: 10px 25px;
        border: 0px;
    }

    tr:nth-child(even) {
        background-color: #FFEFE4;
        color: #A34404;
    }

    th {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #7D3200;
        color: white;
        border: 0px;
        font-size: 20px;
        font-family: Purple Smile;
        letter-spacing: 1px;
    }

    tr {
        padding-top: 12px;
        padding-bottom: 12px;
        text-align: left;
        background-color: #ffcba7;
        color: #A34404;
        border: 2px solid #FFEFE4;
    }

    table tr:first-child th:first-child {
        border-top-left-radius: 20px;
    }

    table tr:first-child th:last-child {
        border-top-right-radius: 20px;
    }

    table tr:last-child td:first-child {
        border-bottom-left-radius: 20px;
    }

    table tr:last-child td:last-child {
        border-bottom-right-radius: 20px;
    }

    .delete-button {
        background-color: transparent;
        border: none;
        cursor: pointer;
        color: #A34404;
        font-size: 20px;
    }
</style>
</head>
<body>
    <div class="container">
        <header>
            <h1>RawrIT</h1>
            <nav>
                <a href="home.html">Home</a>
                <a href="users.html" class="active">Users</a>
                <a href="item_analysis.html">Item Analysis</a>
            </nav>
        </header>
        <main>
            <div class="tabs">
                <button class="tab-button" data-tab="students">Students</button>
            </div>
            <div class="tab-content" id="students">
                <?php include 'fetch_student.php'; ?>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));

                    button.classList.add('active');
                    document.getElementById(button.getAttribute('data-tab')).classList.add('active');
                });
            });

            document.querySelectorAll('.delete-button').forEach(button => {
                button.addEventListener('click', (event) => {
                    const accountNumber = event.target.closest('tr').dataset.accountNumber;
                    const isTeacher = event.target.closest('table').dataset.type === 'teachers';

                    if (confirm(`Are you sure you want to delete this ${isTeacher ? 'teacher' : 'student'}?`)) {
                        fetch('delete_user.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `account_number=${accountNumber}&type=${isTeacher ? 'teacher' : 'student'}`
                        })
                        .then(response => response.text())
                        .then(result => {
                            if (result === 'success') {
                                event.target.closest('tr').remove();
                            } else {
                                alert('Failed to delete the user.');
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>