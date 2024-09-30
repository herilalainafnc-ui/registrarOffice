<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require '../init/head.php'; ?>
    <script>
        $(document).ready(function() {
            function fetchData() {
                $.ajax({
                    url: '../data/data.php',
                    method: 'GET',
                    success: function(response) {
                        $('#content').text(response.message);
                    },
                    error: function() {
                        console.error('Erreur lors de la récupération des données.');
                    }
                });
            }

            // Appel initial pour charger les données
            fetchData();

            // Mettre à jour les données toutes les 5 secondes
            setInterval(fetchData, 1000);
        });
    </script>
</head>
<body>
    <h1>Mise à jour en temps réel</h1>
    <div id="content">Chargement...</div>
</body>
</html>
