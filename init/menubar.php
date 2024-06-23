
<div class="xl:w-2/12 lg:w-3/12 bg-slate-700" style="height: calc(100vh - 48px);">

	<ul class="text-slate-100 text-bold my-3 mx-2">
		
		<a href="./accueil.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.php" OR $page == "student.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-people-fill"></ion-icon>
				</span>
				<span class="title">Liste des étudiants</span>
			
		</li></a>

		<a href="./inscription.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "inscription.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-person-add"></ion-icon>
				</span>
				<span class="title">Ajouter étudiant</span>
			
		</li></a>
		<hr>
		<a href="./accueil.cours.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-list-columns-reverse"></ion-icon>
				</span>
				<span class="title">Liste des cours</span>
			
		</li></a>

		<a href="#" class="toolInactive"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "addCours.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-file-plus"></ion-icon>
				</span>
				<span class="title">Ajouter cours</span>
			
		</li></a>
		<hr>
		<a href="./accueil.prof.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.prof.php" OR $page == "prof.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-person-lines-fill"></ion-icon>
				</span>
				<span class="title">Liste des enseignants</span>
			
		</li></a>

		<a href="#" class="toolInactive"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "addProf.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-person-add"></ion-icon>
				</span>
				<span class="title">Ajouter enseignant</span>
			
		</li></a>
		<hr>
		<a href="#"><li class="toolInactive hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "settings.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-gear-fill"></ion-icon>
				</span>
				<span class="title">Paramètres</span>
			
		</li></a>
		<hr>
		<a class="dropdown-item" href="../app/logout.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1">
			
				<span class="icon">
					<ion-icon class="bi-door-open-fill"></ion-icon>
				</span>
				<span class="title">Déconnecter</span>
			
		</li></a>


	</ul>



</div>


<style type="text/css">
	.icon{
		margin: 20px;
	}
</style>