<?php



namespace raklib\protocol;

#include <rules/RakLibPacket.h>


class UnconnectedPingOpenConnections extends UnconnectedPing{
	public static $ID = MessageIdentifiers::ID_UNCONNECTED_PING_OPEN_CONNECTIONS;
}