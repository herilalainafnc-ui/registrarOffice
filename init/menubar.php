
<div class="xl:w-2/12 lg:w-3/12 bg-slate-700" style="height: calc(100vh - 48px);">

	<ul class="text-slate-100 text-bold my-3 mx-2">
		
		<a href="./accueil.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.php" OR $page == "student.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-people-fill"></ion-icon>
				</span>
				<span class="title">Liste d'étudiant</span>
			
		</li></a>

		<a href="./creat.student.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1">
			
				<span class="icon">
					<ion-icon class="bi-person-add"></ion-icon>
				</span>
				<span class="title">Créer un étudiant</span>
			
		</li></a>
		<hr>
		<a href="./accueil.prof.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.prof.php" OR $page == "prof.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-person-lines-fill"></ion-icon>
				</span>
				<span class="title">Liste d'enseignant</span>
			
		</li></a>

		<a href="#" id="addProf"><li class="hover:bg-cyan-500 py-2 rounded-md my-1">
			
				<span class="icon">
					<ion-icon class="bi-person-add"></ion-icon>
				</span>
				<span class="title">Créer un enseignant</span>
			
		</li></a>
		<hr>
		<a href="./accueil.cours.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "accueil.cours.php" OR $page == "cours.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-list-columns-reverse"></ion-icon>
				</span>
				<span class="title">Liste de cours</span>
			
		</li></a>

		<a href="#" id="addCours"><li class="hover:bg-cyan-500 py-2 rounded-md my-1">
			
				<span class="icon">
					<ion-icon class="bi-file-plus"></ion-icon>
				</span>
				<span class="title">Créer un cours</span>
			
		</li></a>
		<hr>
		<a href="./settings.php"><li class="hover:bg-cyan-500 py-2 rounded-md my-1 <?php 
if($page == "settings.php") {
	echo "bg-cyan-700";
} ?>">
			
				<span class="icon">
					<ion-icon class="bi-gear-fill"></ion-icon>
				</span>
				<span class="title">Paramètres</span>
			
		</li></a>
		<hr>
		<a class="dropdown-item logOut" href="#"><li class="hover:bg-cyan-500 py-2 rounded-md my-1">
			
				<span class="icon">
					<ion-icon class="bi-door-open-fill"></ion-icon>
				</span>
				<span class="title"><b>Déconnecter</b></span>
			
		</li></a>


	</ul>



</div>


<style type="text/css">
	.icon{
		margin: 20px;
	}
</style>