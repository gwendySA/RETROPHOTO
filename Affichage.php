<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Analyse des Localités étrangères</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; }
        #progressBarContainer { width: 100%; background: #eee; margin-top: 20px; }
        #progressBar { height: 20px; background: green; color: white; text-align: center; width: 0%; }
        button { margin-right: 10px; padding: 8px 16px; }
    </style>
</head>
<body>

<h2>🌍 Analyse des Localités étrangères</h2>
<button onclick="lancer()">▶ Démarrer l'analyse</button>
<button id="btnPause" onclick="togglePause()" disabled>⏸ Pause</button>
<button onclick="exporterCSV()">💾 Exporter CSV</button>

<div id="progressBarContainer">
    <div id="progressBar">0%</div>
</div>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Nom Base</th>
        <th>Nom Local</th>
        <th>Pays</th>
    </tr>
    </thead>
    <tbody id="contenu"></tbody>
</table>

<script>
    let source = null;
    let pause = false;
    let buffer = [];

    function lancer() {
        const contenu = document.getElementById("contenu");
        const progressBar = document.getElementById("progressBar");
        const btnPause = document.getElementById("btnPause");

        contenu.innerHTML = "";
        progressBar.style.width = "0%";
        progressBar.innerText = "0%";
        pause = false;
        buffer = [];

        btnPause.disabled = false;
        btnPause.textContent = "⏸ Pause";

        if (source) {
            source.close(); // s'assurer de ne pas créer plusieurs connexions
        }

        source = new EventSource("Localite.php");

        source.onmessage = function(event) {
            if (event.data === "[FIN]") {
                source.close();

                const link = document.createElement("a");
                link.href = "homonymes_stricts.csv";
                link.download = "homonymes_stricts.csv";
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                return;
            }

            try {
                const data = JSON.parse(event.data);

                if (pause) {
                    buffer.push(data); // stocker pour plus tard
                } else {
                    afficher(data);
                }

            } catch (e) {
                console.error("Erreur de parsing JSON :", event.data);
            }
        };

        source.onerror = function(err) {
            console.error("Erreur SSE :", err);
            if (source) source.close();
        };
    }

    function afficher(data) {
        const contenu = document.getElementById("contenu");
        const progressBar = document.getElementById("progressBar");

        if (data.total !== undefined && data.index !== undefined) {
            const pct = Math.round((data.index / data.total) * 100);
            progressBar.style.width = pct + "%";
            progressBar.innerText = pct + "%";
        } else if (data.id) {
            const ligne = `<tr>
                <td>${data.id}</td>
                <td>${data.nomBase}</td>
                <td>${data.nomLocal}</td>
                <td>${data.pays}</td>
            </tr>`;
            contenu.innerHTML += ligne;
        }
    }

    function togglePause() {
        pause = !pause;
        const btn = document.getElementById("btnPause");

        if (pause) {
            btn.textContent = "▶ Reprendre";
        } else {
            btn.textContent = "⏸ Pause";
            // Affiche ce qui a été mis en pause
            buffer.forEach(afficher);
            buffer = [];
        }
    }

    function exporterCSV() {
        const link = document.createElement("a");
        link.href = "homonymes_stricts.csv";
        link.download = "homonymes_stricts.csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }



</script>

</body>
</html>
