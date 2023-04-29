<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backlink Kontrol Aracı</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
    <script>
        async function checkBacklinks(event) {
            event.preventDefault();

            const domain = document.getElementById('domain').value;
            const backlinksTextarea = document.getElementById('backlinks');
            const backlinks = backlinksTextarea.value.split('\n').filter(line => line.trim() !== '');

            const batchSize = 10;
            const totalLinks = backlinks.length;
            let processedLinks = 0;
            let tableHTML = '<tr><th>Backlink URL</th><th>Anahtar Kelimeler</th><th>DoFollow/Nofollow</th><th>HTTP Durumu</th></tr>';

            for (let i = 0; i < totalLinks; i += batchSize) {
                const currentBatch = backlinks.slice(i, i + batchSize);
                const response = await fetch('backlink_check.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ domain, backlinks: currentBatch })
                });

                const result = await response.json();

                result.forEach(row => {
                    tableHTML += `<tr><td>${row.backlink}</td><td>${row.keywords.join(', ')}</td><td>${row.rel}</td><td>${row.httpStatus}</td></tr>`;
                    processedLinks++;

                    const progress = Math.floor((processedLinks / totalLinks) * 100);
                    document.getElementById('progress').innerText = `İşlem Durumu: %${progress} (${processedLinks}/${totalLinks})`;
                });
            }

            document.getElementById('results').innerHTML = tableHTML;
        }
    </script>
</head>
<body>
    <h1>Backlink Kontrol Aracı</h1>
    <form onsubmit="checkBacklinks(event)">
        <label for="domain">Domain:</label>
        <input type="text" name="domain" id="domain">
        <br><br>
        <label for="backlinks">Backlink URL'leri (her satıra bir tane):</label>
        <br>
        <textarea name="backlinks" id="backlinks" rows="10" cols="100"></textarea>
        <br>
        <input type="submit" value="Kontrol Et">
    </form>
    <p id="progress">İşlem Durumu: %0 (0/0)</p>
    <table id="results"></table>
</body>
</html>
