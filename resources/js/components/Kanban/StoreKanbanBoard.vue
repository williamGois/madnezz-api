<template>
  <div class="store-kanban-board">
    <!-- Board Header -->
    <div class="board-header">
      <h2 class="board-title">Kanban por Loja</h2>
      <div class="board-controls">
        <input 
          v-model="searchQuery" 
          type="text" 
          placeholder="Buscar lojas..."
          class="store-search"
        >
        <button @click="refreshBoard" class="btn-refresh">
          <i class="fas fa-sync-alt"></i> Atualizar
        </button>
      </div>
    </div>

    <!-- Board Content -->
    <div class="board-content" v-if="!loading">
      <div class="stores-container" :class="{ 'dragging': isDragging }">
        <StoreColumn
          v-for="store in filteredStores"
          :key="store.store_id"
          :store="store"
          @task-drop="handleTaskDrop"
          @task-drag-start="handleDragStart"
          @task-drag-end="handleDragEnd"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-else class="loading-container">
      <div class="spinner"></div>
      <p>Carregando tarefas...</p>
    </div>

    <!-- Empty State -->
    <div v-if="!loading && filteredStores.length === 0" class="empty-state">
      <i class="fas fa-store-slash"></i>
      <p>Nenhuma loja encontrada</p>
    </div>
  </div>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';
import StoreColumn from './StoreColumn.vue';
import { kanbanAPI } from '@/services/kanbanAPI';

export default {
  name: 'StoreKanbanBoard',
  components: {
    StoreColumn
  },
  setup() {
    const store = useStore();
    const loading = ref(true);
    const searchQuery = ref('');
    const boardData = ref({ board: [], total_stores: 0 });
    const isDragging = ref(false);

    const filteredStores = computed(() => {
      if (!searchQuery.value) {
        return boardData.value.board;
      }
      
      const query = searchQuery.value.toLowerCase();
      return boardData.value.board.filter(store => 
        store.store_name.toLowerCase().includes(query) ||
        store.store_code.toLowerCase().includes(query)
      );
    });

    const loadKanbanData = async () => {
      try {
        loading.value = true;
        const response = await kanbanAPI.getKanbanBoard();
        boardData.value = response.data;
      } catch (error) {
        console.error('Error loading kanban data:', error);
        store.dispatch('showNotification', {
          type: 'error',
          message: 'Erro ao carregar o quadro Kanban'
        });
      } finally {
        loading.value = false;
      }
    };

    const refreshBoard = () => {
      loadKanbanData();
    };

    const handleTaskDrop = async ({ taskId, fromStoreId, toStoreId, newStatus }) => {
      try {
        // Update task with new store and status
        await kanbanAPI.updateTask(taskId, {
          organization_unit_id: toStoreId,
          status: newStatus
        });

        // Reload board data
        await loadKanbanData();
        
        store.dispatch('showNotification', {
          type: 'success',
          message: 'Tarefa movida com sucesso'
        });
      } catch (error) {
        console.error('Error moving task:', error);
        store.dispatch('showNotification', {
          type: 'error',
          message: 'Erro ao mover tarefa'
        });
        // Reload to revert changes
        await loadKanbanData();
      }
    };

    const handleDragStart = () => {
      isDragging.value = true;
    };

    const handleDragEnd = () => {
      isDragging.value = false;
    };

    onMounted(() => {
      loadKanbanData();
    });

    return {
      loading,
      searchQuery,
      filteredStores,
      isDragging,
      refreshBoard,
      handleTaskDrop,
      handleDragStart,
      handleDragEnd
    };
  }
};
</script>

<style scoped>
.store-kanban-board {
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: #f5f5f5;
}

.board-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 2rem;
  background-color: white;
  border-bottom: 1px solid #e0e0e0;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.board-title {
  margin: 0;
  font-size: 1.5rem;
  color: #333;
}

.board-controls {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.store-search {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  width: 250px;
  font-size: 0.9rem;
}

.store-search:focus {
  outline: none;
  border-color: #4CAF50;
}

.btn-refresh {
  padding: 0.5rem 1rem;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-refresh:hover {
  background-color: #45a049;
}

.board-content {
  flex: 1;
  overflow: hidden;
  padding: 1rem;
}

.stores-container {
  display: flex;
  gap: 1rem;
  height: 100%;
  overflow-x: auto;
  padding-bottom: 1rem;
}

.stores-container.dragging {
  cursor: grabbing;
}

.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 400px;
  color: #666;
}

.spinner {
  width: 50px;
  height: 50px;
  border: 3px solid #f3f3f3;
  border-top: 3px solid #4CAF50;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 400px;
  color: #999;
}

.empty-state i {
  font-size: 4rem;
  margin-bottom: 1rem;
}

/* Scrollbar styling */
.stores-container::-webkit-scrollbar {
  height: 8px;
}

.stores-container::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.stores-container::-webkit-scrollbar-thumb {
  background: #888;
  border-radius: 4px;
}

.stores-container::-webkit-scrollbar-thumb:hover {
  background: #555;
}
</style>