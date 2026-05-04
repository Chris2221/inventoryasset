CREATE TABLE AssetMaster(
    PK_AssetMaster int PRIMARY KEY AUTO_INCREMENT,
    AssetTagNumber VARCHAR(50),
    FK_AssetType int,
    BrandManufacturer VARCHAR(100),
    Model VARCHAR(100),
    SerialNumber VARCHAR(100),
    Descriptions TEXT,
    WarrantyExpiryDate DATE,
    PurchasePrice DECIMAL(10, 2),
    PurchaseDate DATE,
    SupplierVendor VARCHAR(100),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
    CreatedBy int,
   `Conditions` int, -- 1=New, 2=Good, 3=Used, 4=Repaired, 5=Damaged, 6=Under Repair, 7=Decommissioned
    AssignedTo int DEFAULT 0,
    Image VARCHAR(500),
    IsArchived int DEFAULT 0,
    ArchivedRemarks TEXT,
    ReasonForRejection TEXT,
    latitude TEXT,
    longitude TEXT,
    Receipt TEXT
);

CREATE TABLE GeneralAssetMaster(
    GeneralAssetMaster int PRIMARY KEY AUTO_INCREMENT,
    Name VARCHAR(500) NOT NULL,
    Location TEXT,
    Quantity int,
    FK_AssetType int,
    Descriptions TEXT,
    Image VARCHAR(500),
    PurchasePrice DECIMAL(10, 2),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy int,
    IsArchived int Default 0,
    ArchivedRemarks TEXT
);

CREATE TABLE HistoryAsset(
    PK_HistoryAsset int PRIMARY KEY AUTO_INCREMENT,
    FK_AssetMaster int,
    FK_Employees int,
    DateAcquired DATE,
    Condition VARCHAR(100),
    Status VARCHAR(30)
);

CREATE TABLE OutboundAssets(
    PK_OutboundAssets int PRIMARY KEY AUTO_INCREMENT,
    Descriptions TEXT,
    Image VARCHAR(500),
    DateAcquired DATE,
    FK_Users int,
    Approvals Json, 
    Status ENUM('Pending', 'Approved', 'Rejected', 'Returned'),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy int,
    DepartureDate DATE,
    ExpectedReturnDate DATE,
    ReturnedDate DATE,
    ExpectedReceiver VARCHAR(500) DEFAULT NULL,
    Approvers TEXT,
    ReasonForRejection TEXT,
    History TEXT
);

CREATE TABLE OutboundAssetsList(
    PK_OutboundAssetsList int PRIMARY KEY AUTO_INCREMENT,
    FK_OutboundAssets int,
    FK_AssetMaster int,
    FK_GeneralAssetMaster int,
    Quantity int,
    isReturned int, -- null: Outbounded 1 : Returned 2: Rejected
    ReturnedDate DATE,
    QuantityReceived int DEFAULT 0
);

CREATE TABLE AssetInventory (
    PK_AssetInventory int PRIMARY KEY AUTO_INCREMENT,
    FK_AssetMaster int,
    Location VARCHAR(100),
    AssignedTo int,
    DateAcquired DATE,
    Conditions ENUM('New', 'Good', 'Needs Repair', 'Retired'),
    Remarks TEXT,
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FK_Users int, --current user who assigned
    Image varchar(200),
    AssignStatus int NOT NULL DEFAULT 0 --'0 - Approval of Assigned | 1 - Assigned | 2 - Approval of Unassigned | 3 - Unassigned '
);

CREATE TABLE AssetType (
    PK_AssetType int PRIMARY KEY AUTO_INCREMENT,
    AssetTypeName VARCHAR(100) NOT NULL,
    created_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FK_Users int,
    Category int --0: Asset Master | --1: General Assets
);

Create TABLE Users(
    PK_Users int PRIMARY KEY AUTO_INCREMENT,
    Username VARCHAR(50) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Name VARCHAR(100) NOT NULL
    Role VARCHAR(50) NOT NULL DEFAULT 'User',
    Status int DEFAULT 1,
    FK_Employees int
);

CREATE TABLE Employees(
    PK_Employees int PRIMARY KEY AUTO_INCREMENT,
    EmployeeID VARCHAR(50) NOT NULL,
    Name VARCHAR(100) NOT NULL,
    Department VARCHAR(100),
    Position VARCHAR(100),
    Email VARCHAR(100),
    PhoneNumber VARCHAR(20),
    Address TEXT,
    DateHired DATE,
    Status ENUM('Active', 'Inactive', 'Terminated')
);


CREATE TABLE AssignApprovals(
    PK_Approvals int PRIMARY KEY AUTO_INCREMENT,
    FK_AssetMaster int,
    FK_AssetInventory int,
    FK_Employees int,
    ApprovalType varchar(200),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy
    FK_Users int,
    IsApproved int DEFAULT 0, --0 = Pending, 1 = Approved, 2 = Rejected
    Reason TEXT,
    OtherReason TEXT,
    Approvers TEXT,
    ReasonForRejection TEXT
);


CREATE TABLE AssetRepairedHistory(
    PK_AssetRepairedHistory int PRIMARY KEY AUTO_INCREMENT,
    FK_AssetMaster int,
    FK_Employees int,
    RepairDate DATE,
    RepairDetails TEXT,
    RepairedDate DATE,
    RepairedBy varchar(200),
    Cost DECIMAL(10, 2),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ServiceOrderImage VARCHAR(500)
);


CREATE TABLE TransferAssets(
    PK_TransferAssets int PRIMARY KEY AUTO_INCREMENT,
    Descriptions TEXT,
    Image VARCHAR(500),
    ReceivedImage VARCHAR(500),
    ReceivedRemarks VARCHAR(500),
    DateAcquired DATE,
    DepartureDate DATE,
    FK_Users int,
    Approvals Json, 
    Status ENUM('Pending', 'Approved', 'Rejected', 'Received'),
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy int,
    ExpectedReceiver VARCHAR(500) DEFAULT NULL,
    ReceivedBy VARCHAR(500) DEFAULT NULL,
    Approvers TEXT,
    ReasonForRejection TEXT,
    DateReceived DATE
);

CREATE TABLE TransferAssetsList(
    PK_TransferAssetsList int PRIMARY KEY AUTO_INCREMENT,
    FK_TransferAssets int,
    FK_AssetMaster int,
    FK_GeneralAssetMaster int,
    Quantity int
);

CREATE TABLE Settings(
    PK_Settings int PRIMARY KEY AUTO_INCREMENT,
    SettingType int, --1 : IT Asset Approver | --2 : General Asset Approver | --3 : Same Day Approver | 4 : Days To Departure
    SettingValue TEXT NOT NULL,
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CreatedBy int
);

CREATE TABLE ActivityLogs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    FK_Users INT NOT NULL,
    Action VARCHAR(255) NOT NULL,
    Details TEXT,
    IpAddress TEXT,
    Timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE GeneralAssetHistory(
    PK_GeneralAssetHistory INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    FK_GeneralAssetMaster INT NOT NULL,
    FK_Employees INT NOT NULL,
    Quantity INT NOT NULL,
    FK_Users INT NOT NULL,
    CreatedOn TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);