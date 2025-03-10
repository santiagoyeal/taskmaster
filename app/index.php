<?php
$servername = "db";
$username = "root";
$password = "rootpassword";
$dbname = "tareas_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



// Insertar nueva tarea
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['task']) && !isset($_POST['update_task'])) {
    $task = $_POST['task'];
    $stmt = $conn->prepare("INSERT INTO tareas (descripcion) VALUES (?)");
    $stmt->bind_param("s", $task);
    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        echo json_encode(['message' => 'Tarea creada exitosamente!', 'id' => $last_id, 'descripcion' => $task]);
    } else {
        echo json_encode(['error' => 'Error al crear tarea.']);
    }
    $stmt->close();
    exit;
}

// Actualizar tarea
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_task']) && isset($_POST['task_id']) && isset($_POST['task'])) {
    $task_id = $_POST['task_id'];
    $task = $_POST['task'];
    $stmt = $conn->prepare("UPDATE tareas SET descripcion = ? WHERE id = ?");
    $stmt->bind_param("si", $task, $task_id);
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Tarea actualizada exitosamente!', 'id' => $task_id, 'descripcion' => $task]);
    } else {
        echo json_encode(['error' => 'Error al actualizar tarea.']);
    }
    $stmt->close();
    exit;
}

// Eliminar tarea
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task']) && isset($_POST['task_id'])) {
    $task_id = $_POST['task_id'];
    $stmt = $conn->prepare("DELETE FROM tareas WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    if ($stmt->execute()) {
        echo json_encode(['message' => 'Tarea eliminada exitosamente!', 'id' => $task_id]);
    } else {
        echo json_encode(['error' => 'Error al eliminar tarea.']);
    }
    $stmt->close();
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tareas</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
<style>
    /* Fade transition */
    .fade-enter-active, .fade-leave-active {
        transition: opacity 1s ease-in-out;
    }
    .fade-enter, .fade-leave-to /* .fade-leave-active in <2.1.8 */ {
        opacity: 0;
    }

    /* Task transition */
    .task-enter-active, .task-leave-active {
        transition: opacity 1s ease-in-out;
    }
    .task-enter, .task-leave-to /* .task-leave-active in <2.1.8 */ {
        opacity: 0;
    }

    /* General alert styles */
    .alert {
        padding: 12px 20px;
        margin-top: 20px;
        background-color: #1877F2; /* Facebook blue */
        color: white;
        border-radius: 25px;
        text-align: center;
        font-weight: bold;
        font-size: 14px;
    }

    .alert-error {
        background-color: #F44336; /* Red for errors */
        border-radius: 25px;
    }

    .alert-success {
        background-color: #4CAF50; /* Green for success */
        border-radius: 25px;
    }

    .warning-message {
        color: #FF5722;
        font-style: italic;
    }

    /* Task card styles */
    .task-card {
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 12px;
        background-color: #ffffff;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
    }

    .task-card:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .task-card-header {
        font-weight: bold;
        color: #1877F2; /* Facebook blue for headers */
        font-size: 16px;
        margin-bottom: 10px;
    }

    .task-card-body {
        margin-top: 10px;
        color: #333;
        font-size: 14px;
    }

    .task-card button {
        margin-right: 10px;
        background-color: #1877F2;
        color: white;
        padding: 10px 15px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.3s ease;
    }

    .task-card button:hover {
        background-color: #145db2;
    }

    .task-card button:disabled {
        background-color: #ddd;
        cursor: not-allowed;
    }

    .task-card input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 25px;
        margin-top: 12px;
        font-size: 14px;
        transition: border 0.3s ease;
    }

    .task-card input:focus {
        border-color: #1877F2;
        outline: none;
    }
</style>

</head>
<body>
<div id="app">
    <h1>Gestión de Tareas</h1>
    <form @submit.prevent="addTask">
        <input type="text" v-model="newTask" placeholder="Nueva tarea" required>
        <button type="submit" :disabled="!newTask.trim()">Agregar Tarea</button>
        <p v-if="!newTask.trim()" class="warning-message">Por favor ingresa una tarea.</p>
    </form>

    <div v-if="isLoading" class="alert">Cargando...</div>

    <div v-if="message" class="alert" :class="messageClass">{{ message }}</div>

    <div v-if="tasks.length === 0 && !isLoading" class="alert">
        No hay tareas disponibles. ¡Agrega una!
    </div>

    <div v-for="task in tasks" :key="task.id" class="task-card">
        <div class="task-card-header">
            <span v-if="editingTask && editingTask.id === task.id">
                <input v-model="editingTask.descripcion" type="text" placeholder="Editar tarea...">
            </span>
            <span v-else>{{ task.descripcion }}</span>
        </div>
        <div class="task-card-body">
            <!-- Si no estamos editando la tarea, muestra "Editar" -->
            <button @click="editTask(task)">Editar</button>
            <!-- Si estamos editando la tarea, muestra "Guardar" -->
            <button @click="saveTask(task)">Guardar</button>
            <button @click="deleteTask(task.id)">Eliminar</button>
        </div>
    </div>
</div>

<script>
    new Vue({
        el: '#app',
        data: {
            tasks: [],
            newTask: '',
            message: '',
            messageClass: '', // Para el estilo del mensaje (exitoso o de error)
            editingTask: null,
            isLoading: false,
            filterStatus: 'all'
        },
        created() {
            this.fetchTasks();
        },
        methods: {
            fetchTasks() {
                this.isLoading = true;
                fetch('index.php')
                    .then(response => response.json())
                    .then(data => {
                        this.tasks = data;
                        this.isLoading = false;
                    })
                    .catch(error => {
                        console.error('Error al cargar tareas:', error);
                        this.isLoading = false;
                    });
            },
            addTask() {
                const task = this.newTask;
                this.newTask = '';

                fetch('index.php', {
                    method: 'POST',
                    body: new URLSearchParams({ task })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.message) {
                        this.tasks.push({ id: data.id, descripcion: data.descripcion });
                        this.message = data.message;
                        this.messageClass = 'alert-success';
                    } else {
                        this.message = data.error;
                        this.messageClass = 'alert-error';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.message = 'Error al agregar tarea.';
                    this.messageClass = 'alert-error';
                });
            },
            editTask(task) {
                // Si ya estamos editando la misma tarea, no hacer nada más
                if (this.editingTask && this.editingTask.id === task.id) return;

                // Si estamos editando otra tarea, guardar la anterior antes de permitir la edición
                if (this.editingTask) {
                    this.saveTask(this.editingTask);
                }

                this.editingTask = Object.assign({}, task); // Hacemos una copia de la tarea para la edición
            },
            saveTask(task) {
                fetch('index.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        update_task: true,
                        task_id: task.id,
                        task: this.editingTask.descripcion
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.message) {
                        task.descripcion = this.editingTask.descripcion; // Actualizamos la tarea localmente
                        this.editingTask = null; // Limpiamos la tarea en edición
                        this.message = data.message;
                        this.messageClass = 'alert-success';
                    } else {
                        this.message = data.error;
                        this.messageClass = 'alert-error';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.message = 'Error al actualizar tarea.';
                    this.messageClass = 'alert-error';
                });
            },
            deleteTask(taskId) {
                fetch('index.php', {
                    method: 'POST',
                    body: new URLSearchParams({
                        delete_task: true,
                        task_id: taskId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.message) {
                        this.tasks = this.tasks.filter(task => task.id !== taskId);
                        this.message = data.message;
                        this.messageClass = 'alert-success';
                    } else {
                        this.message = data.error;
                        this.messageClass = 'alert-error';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.message = 'Error al eliminar tarea.';
                    this.messageClass = 'alert-error';
                });
            }
        }
    });
</script>
</body>
</html>
