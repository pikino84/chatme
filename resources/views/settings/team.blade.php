<x-app-layout>
    <div class="max-w-4xl mx-auto py-8 px-4">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Miembros del Equipo</h2>
            <a href="{{ route('settings.show') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Volver a Configuración
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 px-4 py-2 bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300 text-sm rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 px-4 py-2 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 text-sm rounded">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Add member form --}}
        @can('users.create')
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 mb-6">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Agregar Miembro</h3>
            <form method="POST" action="{{ route('settings.team.store') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                    <input type="text" name="name" required value="{{ old('name') }}" placeholder="Juan Pérez"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Email</label>
                    <input type="email" name="email" required value="{{ old('email') }}" placeholder="juan@email.com"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Contraseña</label>
                    <input type="password" name="password" required placeholder="Mín. 8 caracteres"
                           class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Rol</label>
                    <select name="role" class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                        <option value="agent">Agente</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="org_admin">Admin</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                        Agregar
                    </button>
                </div>
            </form>
        </div>
        @endcan

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Nombre</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Rol</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Estado</th>
                        @can('users.update')
                            <th class="px-4 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Acciones</th>
                        @endcan
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($users as $user)
                        <tr class="{{ $user->id === auth()->id() ? 'bg-indigo-50/50 dark:bg-indigo-900/10' : '' }}">
                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                {{ $user->name }}
                                @if($user->id === auth()->id())
                                    <span class="text-xs text-gray-400">(tú)</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs px-2 py-0.5 rounded-full
                                    @if($user->roles->first()?->name === 'org_admin') bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300
                                    @elseif($user->roles->first()?->name === 'supervisor') bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300
                                    @else bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300
                                    @endif">
                                    {{ $user->roles->first()?->name ?? 'none' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($user->is_active)
                                    <span class="text-xs text-green-600 dark:text-green-400">Activo</span>
                                @else
                                    <span class="text-xs text-red-500 dark:text-red-400">Inactivo</span>
                                @endif
                            </td>
                            @can('users.update')
                                <td class="px-4 py-3">
                                    @if($user->id !== auth()->id())
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('settings.team.role', $user) }}" class="flex items-center gap-1">
                                                @csrf
                                                <select name="role" class="text-xs rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                                                    <option value="org_admin" @selected($user->roles->first()?->name === 'org_admin')>org_admin</option>
                                                    <option value="supervisor" @selected($user->roles->first()?->name === 'supervisor')>supervisor</option>
                                                    <option value="agent" @selected($user->roles->first()?->name === 'agent')>agent</option>
                                                </select>
                                                <button type="submit" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline">Asignar</button>
                                            </form>

                                            <form method="POST" action="{{ route('settings.team.toggle', $user) }}">
                                                @csrf
                                                <button type="submit" class="text-xs {{ $user->is_active ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800' }}">
                                                    {{ $user->is_active ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
