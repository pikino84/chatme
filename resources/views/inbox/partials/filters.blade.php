<form method="GET" action="{{ route('inbox') }}" class="p-3 border-b border-gray-200 dark:border-gray-700 space-y-2">
    <input type="text" name="search" value="{{ request('search') }}"
           placeholder="Buscar contactos..."
           class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:ring-indigo-500 focus:border-indigo-500">

    <div class="flex gap-2">
        <select name="status" class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">Todos los estados</option>
            <option value="open" @selected(request('status')==='open')>Abierto</option>
            <option value="pending" @selected(request('status')==='pending')>Pendiente</option>
            <option value="closed" @selected(request('status')==='closed')>Cerrado</option>
        </select>

        <select name="channel_id" class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">Todos los canales</option>
            @foreach($channels as $ch)
                <option value="{{ $ch->id }}" @selected(request('channel_id')==$ch->id)>{{ $ch->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="flex gap-2">
        <select name="assigned_user_id" class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
            <option value="">Todos los agentes</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" @selected(request('assigned_user_id')==$agent->id)>{{ $agent->name }}</option>
            @endforeach
        </select>

        <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-md">
            Filtrar
        </button>
    </div>
</form>
