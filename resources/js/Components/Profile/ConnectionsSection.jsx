import React, { useState } from 'react';
import GlassCard from '../UI/GlassCard';
import GlassButton from '../UI/GlassButton';

const ConnectionsSection = () => {
    const [isConnected, setIsConnected] = useState(false);
    const [rootFolder, setRootFolder] = useState('');
    const [showCreateFolder, setShowCreateFolder] = useState(false);
    const [newFolderName, setNewFolderName] = useState('');
    const [subfolders, setSubfolders] = useState([]);
    const [showCreateSubfolder, setShowCreateSubfolder] = useState(false);
    const [newSubfolderName, setNewSubfolderName] = useState('');

    const handleConnect = () => {
        // Simular conexión con Google Drive
        setIsConnected(true);
    };

    const handleSetRootFolder = () => {
        if (rootFolder.trim()) {
            setShowCreateFolder(false);
            setNewFolderName('');
        }
    };

    const handleCreateFolder = () => {
        if (newFolderName.trim()) {
            setRootFolder(newFolderName);
            setShowCreateFolder(false);
            setNewFolderName('');
        }
    };

    const handleCreateSubfolder = () => {
        if (newSubfolderName.trim()) {
            setSubfolders([...subfolders, {
                id: Date.now(),
                name: newSubfolderName,
                createdAt: new Date().toLocaleDateString()
            }]);
            setNewSubfolderName('');
            setShowCreateSubfolder(false);
        }
    };

    return (
        <div className="space-y-6">
            <div className="content-header">
                <div>
                    <h1 className="page-title">Conexiones</h1>
                    <p className="page-subtitle">Conecta tus servicios de almacenamiento en la nube</p>
                </div>
            </div>

            <div className="content-grid">
                {/* Google Drive Connection */}
                <GlassCard className="col-span-full">
                    <h2 className="card-title">
                        <span className="card-icon">📁</span>
                        Google Drive
                    </h2>
                    
                    {!isConnected ? (
                        <div className="text-center py-8">
                            <div className="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-blue-500/20 to-purple-500/20 rounded-full flex items-center justify-center">
                                <span className="text-3xl">📁</span>
                            </div>
                            <p className="text-gray-300 mb-6">
                                Conecta tu cuenta de Google Drive para sincronizar tus reuniones y archivos
                            </p>
                            <GlassButton onClick={handleConnect} variant="primary">
                                🔗 Conectar con Google Drive
                            </GlassButton>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            <div className="flex items-center gap-3 p-4 bg-green-500/10 border border-green-500/20 rounded-lg">
                                <span className="text-green-400 text-xl">✅</span>
                                <div>
                                    <p className="text-green-400 font-semibold">Conectado exitosamente</p>
                                    <p className="text-gray-400 text-sm">Tu cuenta de Google Drive está vinculada</p>
                                </div>
                            </div>

                            {/* Root Folder Configuration */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold text-white">Configuración de Carpeta</h3>
                                
                                {!rootFolder ? (
                                    <div className="space-y-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-300 mb-2">
                                                Pegar ruta de carpeta existente
                                            </label>
                                            <input
                                                type="text"
                                                placeholder="/Mi Drive/Juntify/Reuniones"
                                                className="w-full px-4 py-3 bg-white/5 border border-blue-500/30 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-400/20"
                                                onChange={(e) => setRootFolder(e.target.value)}
                                            />
                                        </div>
                                        
                                        <div className="flex gap-3">
                                            <GlassButton onClick={handleSetRootFolder} variant="primary">
                                                📁 Establecer Carpeta
                                            </GlassButton>
                                            <GlassButton 
                                                onClick={() => setShowCreateFolder(true)} 
                                                variant="secondary"
                                            >
                                                ➕ Crear Nueva Carpeta
                                            </GlassButton>
                                        </div>

                                        {showCreateFolder && (
                                            <div className="p-4 bg-white/5 border border-blue-500/20 rounded-lg">
                                                <label className="block text-sm font-medium text-gray-300 mb-2">
                                                    Nombre de la nueva carpeta
                                                </label>
                                                <div className="flex gap-3">
                                                    <input
                                                        type="text"
                                                        placeholder="Juntify Reuniones"
                                                        value={newFolderName}
                                                        onChange={(e) => setNewFolderName(e.target.value)}
                                                        className="flex-1 px-4 py-2 bg-white/5 border border-blue-500/30 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-400"
                                                    />
                                                    <GlassButton onClick={handleCreateFolder} variant="primary" size="sm">
                                                        Crear
                                                    </GlassButton>
                                                    <GlassButton 
                                                        onClick={() => setShowCreateFolder(false)} 
                                                        variant="secondary" 
                                                        size="sm"
                                                    >
                                                        Cancelar
                                                    </GlassButton>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        <div className="p-4 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                                            <div className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-blue-400 font-semibold">Carpeta Raíz Configurada</p>
                                                    <p className="text-gray-300 text-sm">{rootFolder}</p>
                                                </div>
                                                <GlassButton 
                                                    onClick={() => setRootFolder('')} 
                                                    variant="secondary" 
                                                    size="sm"
                                                >
                                                    Cambiar
                                                </GlassButton>
                                            </div>
                                        </div>

                                        {/* Subfolders Management */}
                                        <div>
                                            <div className="flex items-center justify-between mb-4">
                                                <h4 className="text-md font-semibold text-white">Subcarpetas</h4>
                                                <GlassButton 
                                                    onClick={() => setShowCreateSubfolder(true)} 
                                                    variant="primary" 
                                                    size="sm"
                                                >
                                                    ➕ Nueva Subcarpeta
                                                </GlassButton>
                                            </div>

                                            {showCreateSubfolder && (
                                                <div className="p-4 bg-white/5 border border-blue-500/20 rounded-lg mb-4">
                                                    <label className="block text-sm font-medium text-gray-300 mb-2">
                                                        Nombre de la subcarpeta
                                                    </label>
                                                    <div className="flex gap-3">
                                                        <input
                                                            type="text"
                                                            placeholder="Reuniones Enero 2025"
                                                            value={newSubfolderName}
                                                            onChange={(e) => setNewSubfolderName(e.target.value)}
                                                            className="flex-1 px-4 py-2 bg-white/5 border border-blue-500/30 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-400"
                                                        />
                                                        <GlassButton onClick={handleCreateSubfolder} variant="primary" size="sm">
                                                            Crear
                                                        </GlassButton>
                                                        <GlassButton 
                                                            onClick={() => setShowCreateSubfolder(false)} 
                                                            variant="secondary" 
                                                            size="sm"
                                                        >
                                                            Cancelar
                                                        </GlassButton>
                                                    </div>
                                                </div>
                                            )}

                                            <div className="space-y-2">
                                                {subfolders.length === 0 ? (
                                                    <p className="text-gray-400 text-center py-4">
                                                        No hay subcarpetas creadas
                                                    </p>
                                                ) : (
                                                    subfolders.map((folder) => (
                                                        <div key={folder.id} className="flex items-center justify-between p-3 bg-white/5 border border-blue-500/10 rounded-lg">
                                                            <div className="flex items-center gap-3">
                                                                <span className="text-blue-400">📁</span>
                                                                <div>
                                                                    <p className="text-white font-medium">{folder.name}</p>
                                                                    <p className="text-gray-400 text-xs">Creada el {folder.createdAt}</p>
                                                                </div>
                                                            </div>
                                                            <GlassButton 
                                                                onClick={() => setSubfolders(subfolders.filter(f => f.id !== folder.id))} 
                                                                variant="danger" 
                                                                size="sm"
                                                            >
                                                                🗑️
                                                            </GlassButton>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </GlassCard>
            </div>
        </div>
    );
};

export default ConnectionsSection;